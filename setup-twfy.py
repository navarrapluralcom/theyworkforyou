#!/usr/bin/python2.5

# This script takes requires a more-or-less basic lenny UML root
# filesystem image and sets up TWFY on it.  To create a suitable UML
# root image, run the "create-rootfs.py" script to create an image
# called uml-rootfs-pristine, for example with:
#
#   sudo ./create-rootfs.py 1600 uml-rootfs-pristine tmp mark:mark

from common import *
from subprocess import call, check_call, Popen
import time
import re
import sys
from optparse import OptionParser
from BeautifulSoup import BeautifulSoup
from browser import fake_browser
import cgi

parser = OptionParser(usage="Usage: %prog [OPTIONS]")
parser.add_option('-r', '--reuse-image', dest="reuse", action="store_true",
                  default=False, help="resuse the root fs image instead of starting anew")
parser.add_option('-o', '--output-directory', dest="output_directory",
                  help="override the default test output directory (./output/[TIMESTAMP]/)")
options,args = parser.parse_args()

if len(args) != 0:
    parser.print_help()
    sys.exit(1)

# FIXME: check that there isn't already a UML machine running; that
# can get confusing...

# git_url = None
git_url = "git://crumble.dyndns.org/git/mysociety"

link_command = None

if options.output_directory:
    output_directory = options.output_directory
else:
    iso_time = time.strftime("%Y-%m-%dT%H:%M:%S",time.gmtime())
    output_directory = "output/%s/" % (iso_time,)
    latest_symlink = "output/latest"
    if os.path.exists(latest_symlink):
        call(["rm",latest_symlink])
    link_command = ["ln","-s",iso_time,latest_symlink]

check_call(["mkdir","-p",output_directory])

if link_command:
    print "Calling "+" ".join(link_command)
    call(link_command)

# Restart from standard root filesystem, perhaps generated from
# create-rootfs.py:
if not options.reuse:
    check_call(["cp",
                "-v",
                "--sparse=always",
                "uml-rootfs-pristine",
                "uml-rootfs-test"])

initial_mysql_root_password = ""
if False:
    initial_mysql_root_password += " --password="+configuration['MYSQL_ROOT_PASSWORD']

# We switch UML machines frequently, so remove the host key for the
# UML machine's IP address.
check_call(["ssh-keygen","-R",configuration['UML_SERVER_IP']])

# Check if the UML machine is already running:

p = Popen("echo version|uml_mconsole TWFY", stdout=PIPE, shell=True)
console_version = p.communicate()[0]
uml_already_running = re.search('^.TWFY. OK',console_version)

if uml_already_running:
    print "UML machine is already running, not starting a new one."
    if not web_server_working():
        print "... but the web server doesn't seem to be up."
        sys.exit(1)
else:
    print "UML machine was not running, starting one up."
    uml_stdout = open(output_directory+"/uml.stdout","w")
    uml_stderr = open(output_directory+"/uml.stderr","w")
    popen_object = Popen("./start-server.py",
                         stdout=uml_stdout,
                         stderr=uml_stderr)
    wait_for_web_server_or_exit(popen_object.pid)
    check_call(["stty","sane"])

if not user_exists("alice"):
    print "==  Going to try to call adduser"
    result = ssh("adduser --disabled-password --gecos 'An Example User' alice",user="root")
    if result != 0:
        raise Exception, "Failed to create the user alice"

alice_ssh_directory = "/home/alice/.ssh"

print "==  Going to test for alice's ssh directory"
if not path_exists_in_uml(alice_ssh_directory):
    result = ssh("mkdir -m 0700 "+alice_ssh_directory,user="root")
    if result != 0:
        raise Exception, "Failed to create alice's ssh directory"
    result = ssh("chown alice.alice "+alice_ssh_directory,user="root")

alice_authorized_keys = alice_ssh_directory + "/authorized_keys"

if not path_exists_in_uml(alice_authorized_keys):
    result = scp("id_dsa.alice.pub",alice_authorized_keys,user="root")
    if result != 0:
        raise Exception, "Failed to copy over alice's public key"
    result = ssh("chown alice.alice "+alice_authorized_keys,user="root")
    if result != 0:
        raise Exception, "Failed to chown alice's authorized_keys file"

start_all_coverage = uml_date()

# Now install some extra packages that we'll need:
result = ssh("apt-get update",user="root")
if result != 0:
    raise Exception, "Updating the package information failed"

result = ssh("DEBIAN_FRONTEND=noninteractive apt-get install --yes locales",user="root")
if result != 0:
    raise Exception, "Installing additional packages failed"

result = ssh("echo 'en_GB.UTF-8 UTF-8' > /etc/locale.gen",user="root")
if result != 0:
    raise Exception, "Overwriting /etc/locale.gen failed"

result = ssh("/usr/sbin/locale-gen",user="root")
if result != 0:
    raise Exception, "Running locale-gen failed"

result = ssh("DEBIAN_FRONTEND=noninteractive apt-get install --yes mysql-server php5-curl php5-mysql php5-xdebug subversion rsync python2.5-minimal libxml-twig-perl php5-cli",user="root")
if result != 0:
    raise Exception, "Installing additional packages failed"

result = ssh("apt-get clean",user="root")
if result != 0:
    raise Exception, "Removing dowloaded packages failed"

# Checkout the mysociety module from mySociety CVS into alice's home
# directory, or if we've specified a git_url, use that instead:
if not path_exists_in_uml("/home/alice/mysociety"):
    if git_url:
        # If there's a local copy of the repository, just clone that:
        if path_exists_in_uml("/home/alice/mysociety.git/"):
            result = ssh("git clone /home/alice/mysociety.git/ /home/alice/mysociety")
        else:
            result = ssh("git clone git://crumble.dyndns.org/git/mysociety")
    else:
        result = ssh("cvs -d :pserver:anonymous@cvs.mysociety.org:/repos co mysociety")
    if result != 0:
        raise Exception, "Checking out the mysociety module from version control failed"

# In case the database already exists, drop it:
ssh("mysqladmin -f -u root"+
    initial_mysql_root_password+
    " drop twfy",user="root")

# Create the database:
result = ssh("mysqladmin -u root"+
             initial_mysql_root_password+
             " create twfy",user="root")
if result != 0:
    raise Exception, "Creating the twfy database failed"

# Grant all permissions to a 'twfy' user on that database:
result = ssh("echo \"GRANT ALL ON twfy.* TO twfy@localhost IDENTIFIED BY '"+
             configuration['MYSQL_TWFY_PASSWORD']+"'\" | "+
             "mysql -u root"+initial_mysql_root_password)
if result != 0:
    raise Exception, "Failed to GRANT ALL on twfy to the twfy MySQL user"

# Create the database schema:
run_ssh_test(output_directory,
             "mysql -u twfy --password="+
             configuration['MYSQL_TWFY_PASSWORD']+
             " twfy < /home/alice/mysociety/twfy/db/schema.sql",
             test_name="Creating the TWFY database schema",
             test_short_name="create-schema")

# Create the general configuration file from a template:
untemplate("general.template","general")

# Copy over the general configuration file:
result = scp("general","/home/alice/mysociety/twfy/conf")
if result != 0:
    raise Exception, "Failed to scp the general configuration file"

# Create a world-writable directory for coverage data:
coverage_directory = "/home/alice/twfy-coverage/"
if not path_exists_in_uml(coverage_directory):
    result = ssh("mkdir -m 0777 "+coverage_directory)
    if result != 0:
        raise Exception, "Failed to create coverage data directory"

# Remove any old data from that directory:
result = ssh("rm -f "+coverage_directory+"/*")
if result != 0:
    raise Exception, "Failed to clean the coverage data directory"

instrument_script = "/usr/local/bin/add-php-instrumentation.py"

# Copy over the script to add instrumentation file:
result = scp("add-php-instrumentation.py",
             instrument_script,
             user="root")
if result != 0:
    raise Exception, "Failed to copy over the add-php-instrumentation.py file"

# Make it executable:
result = ssh("chmod a+rx "+instrument_script,user="root")
if result != 0:
    raise Exception, "Failed to make the add-php-instrumentation.py file executable"

result = ssh("cd ~/mysociety/ && git checkout -f master")
if result != 0:
    raise Exception, "Couldn't switch to branch master"

# Add the instrumentation:
ssh_result = ssh(instrument_script+" /home/alice/mysociety/twfy/www/",capture=True)
if ssh_result.return_value != 0:
    raise Exception, "Instrumenting the TWFY PHP code failed."

instrumented_files = re.split('[\r\n]+',ssh_result.stdout_data)
print "File list:"
for i in instrumented_files:
    print "  "+i

# Remove any old branch called instrumented, since we might be running
# with an old image where such was created:
result = ssh("cd ~/mysociety/ && git branch -D instrumented")

result = ssh("cd ~/mysociety/ && git checkout -b instrumented")
if result != 0:
    raise Exception, "Failed to create a new branch for the instrumented version"

# Copy over the instrument.php file:
result = scp("instrument.php",
             "/home/alice/mysociety/twfy/www/includes/instrument.php")
if result != 0:
    raise Exception, "Failed to copy over the instrument.php file"

result = ssh("cd ~/mysociety/twfy/www && git add includes/instrument.php "+" ".join(instrumented_files))
if result != 0:
    raise Exception, "Failed to add the instrumented files to the index"

result = ssh("cd ~/mysociety/ && git commit -m 'An instrumented version of the TWFY code'")
if result != 0:
    raise Exception, "Creating a new commit failed."

# Set up the Apache virtual host:
result = scp("etc-apache2-sites-available-twfy",
             "/etc/apache2/sites-available/twfy",
             user="root")
if result != 0:
    raise Exception, "Failed to copy over the VirtualHost configuration"

result = scp("etc-apache2-ports-conf",
             "/etc/apache2/ports.conf",
             user="root")
if result != 0:
    raise Exception, "Failed to copy over the ports.conf file"

# Run a2enmod:
run_ssh_test(output_directory,
             "a2enmod rewrite",
             user="root",
             test_name="Enabling mod_rewrite",
             test_short_name="mod-rewrite")

# Run a2ensite:
run_ssh_test(output_directory,
             "a2ensite twfy",
             user="root",
             test_name="Enabling the TWFY virtual host",
             test_short_name="a2ensite-twfy")

# Restart Apache on the server:
run_ssh_test(output_directory,
             "/etc/init.d/apache2 reload",
             user="root",
             test_name="Restarting Apache",
             test_short_name="restart-apache")

# Check out parlparse:
run_ssh_test(output_directory,
             "svn co http://project.knowledgeforge.net/ukparse/svn/trunk/parlparse",
             test_name="Checking out parlparse from svn",
             test_short_name="svn-co-parlparse")

# Import the member data:
run_ssh_test(output_directory,
             "cd /home/alice/mysociety/twfy/scripts && ./xml2db.pl --members --all",
             test_name="Importing the member data",
             test_short_name="import-member-data")

run_http_test(output_directory,
              "/msps/",
              test_name="Fetching basic MSPs page",
              test_short_name="basic-MSPs")

run_http_test(output_directory,
              "/mp/gordon_brown/kirkcaldy_and_cowdenbeath",
              test_name="Fetching Gordon Brown's page",
              test_short_name="gordon-brown")



end_all_coverage = uml_date()

output_filename_all_coverage = os.path.join(output_directory,"coverage")

coverage_data = coverage_data_between(start_all_coverage,end_all_coverage)
fp = open(output_filename_all_coverage,"w")
fp.write(coverage_data)
fp.close()

used_source_directory = os.path.join(output_directory,"mysociety")

check_call(["mkdir","-p",used_source_directory])

rsync_from_guest("/home/alice/mysociety/twfy/",
                 os.path.join(used_source_directory,"twfy"),
                 user="alice")

rsync_from_guest("/home/alice/mysociety/phplib/",
                 os.path.join(used_source_directory,"phplib"),
                 user="alice")

# fake_browser

report_index_filename = os.path.join(output_directory,"report.html")
fp = open(report_index_filename,"w")

# Generate complete coverage report:

coverage_report_directory = "coverage-report"
generate_coverage(output_filename_all_coverage,
                  os.path.join(output_directory,coverage_report_directory),
                  used_source_directory)

fp.write('''<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<head>
<title>They Work For You Test Reports</title>
</head>
<body style="background-color: #ffffff">
<h2>They Work For You Test Reports</h2>
<p><a href="coverage-report/coverage.html">Code coverage report for all tests.</a>
</p>

''')

for t in all_tests:
    print "=============="
    print str(t)

    passed_colour = "#96ff81"
    failed_colour = "#ff8181"

    background_colour = passed_colour

    fp.write("<div style=\"border-width=1px; background-color: %s\">\n"%(passed_colour,))
    fp.write("<h3>%s</h3>\n" % (t.test_name,))
    fp.write("<h4>%s</h4>\n" % (t.get_id_and_short_name(),))
    fp.write("<pre\n>")
    fp.write(cgi.escape(file_to_string(os.path.join(t.test_output_directory,"info"))))
    fp.write("</pre>\n")
    if t.test_type == TEST_HTTP:
        # Generate coverage information:
        coverage_data_file = os.path.join(t.test_output_directory,"coverage")
        coverage_report_directory = os.path.join(t.test_output_directory,"coverage-report")
        print "Using parameters:"
        print "coverage_data_file: "+coverage_data_file
        print "coverage_report_directory: "+coverage_report_directory
        print "used_source_directory: "+used_source_directory
        generate_coverage(coverage_data_file,
                          coverage_report_directory,
                          used_source_directory)
        fp.write("<p><a href=\"%s\">Code coverage for this test.</a></p>\n" % (coverage_report_directory+"report.html",))
        if t.full_image_filename:
            # fp.write("<div style=\"float: right\">")
            fp.write("<div>")
            relative_full_image_filename = re.sub(re.escape(output_directory),'',t.full_image_filename)
            relative_thumbnail_image_filename = re.sub(re.escape(output_directory),'',t.thumbnail_image_filename)
            fp.write("<a href=\"%s\"><img src=\"%s\"></a>" % (relative_full_image_filename,relative_thumbnail_image_filename))
            fp.write("</div>")
    elif t.test_type == TEST_SSH:
        for s in ("stdout","stderr"):
            fp.write("<h4>%s</h4>" % (s,))
            fp.write("<div style=\"background-color: #bfbfbf\"><pre>")
            fp.write(cgi.escape(file_to_string(os.path.join(t.test_output_directory,s))))
            fp.write("</pre></div>")
    fp.write("</div>\n")

fp.write('''</table>
</body>
</html>''')
fp.close()
