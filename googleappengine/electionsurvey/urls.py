from django.conf.urls.defaults import *
from django.views.generic.simple import direct_to_template, redirect_to
from django.http import HttpResponseRedirect, HttpResponsePermanentRedirect

import views
import models
from feeds import LatestAnswers

# Uncomment the next two lines to enable the admin:
# from django.contrib import admin
# admin.autodiscover()

feeds = {'latest_answers':LatestAnswers}

urlpatterns = patterns('',
    url(r'^feeds/(?P<url>.*)/$',
        'django.contrib.syndication.views.feed',
        {'feed_dict': feeds}),

    url(r'^api$', views.api_index),
    url(r'^api/$', redirect_to, {'url' : '/api'} ),
    url(r'^survey/stats.json$', views.survey_stats_json),
    url(r'^survey/candidacies.json$', views.survey_candidacies_json),
    url(r'^survey/candidates.json$', views.survey_candidates_json),
    url(r'^survey/seats.json$', views.survey_seats_json),
    url(r'^survey/issues.json$', views.survey_refined_issues_json),
    url(r'^survey/responses.json$', views.survey_responses_json),

    url(r'^survey$', views.survey_candidacy),
    url(r'^survey/autosave/(?P<token>.+)$', views.survey_autosave),
    url(r'^survey/vote/(?P<token>.+)$', views.survey_useful_save),
    url(r'^survey/seats$', views.survey_seats_list),
    url(r'^survey/seats/(?P<code>.+)$', views.survey_seats),
    url(r'^survey/(?P<token>.+)$', views.survey_candidacy),

    url(r'^survey/$', redirect_to, {'url' : '/survey'} ),
    url(r'^survey/seat/$', redirect_to, {'url' : '/survey/seat'} ),

    url(r'^$', views.quiz_ask_postcode),
    url(r'^quiz$', views.quiz_ask_postcode),
    url(r'^quiz/subscribe$', views.quiz_subscribe),
    url(r'^quiz/seats/(?P<code>.+)$', views.quiz_by_code),
    url(r'^quiz/(?P<postcode>.+)$', views.quiz_by_postcode),

    url(r'^quiz/$', redirect_to, {'url' : '/quiz'} ),

    url(r'^admin/?$', redirect_to, {'url' : '/admin/index'} ),
    url(r'^admin/index$', views.admin_index),
    url(r'^admin/stats$', views.admin_stats),
    url(r'^admin/responses$', views.admin_responses),

    url(r'^task/invite_candidacy_survey/(?P<candidacy_key_name>[\d-]+)$', views.task_invite_candidacy_survey),
    url(r'^task/average_response_by_party/(?P<party_key_name>[\d-]+)/(?P<refined_issue_key_name>[\d-]+)$', views.task_average_response_by_party),

    url(r'^guardian_candidate/(?P<aristotle_id>\d+)$', views.guardian_candidate),
    url(r'^guardian_candidate/(?P<raw_name>.+)/(?P<raw_const_name>.+)$', views.guardian_candidate),
    url(r'^guardian_candidate/(?P<raw_name>.+)$', views.guardian_candidate),

    # url(r'^fooble$', views.fooble),

    # Example:
    # (r'^electionsurvey/', include('electionsurvey.foo.urls')),

    # Uncomment the admin/doc line below and add 'django.contrib.admindocs' 
    # to INSTALLED_APPS to enable admin documentation:
    # (r'^admin/doc/', include('django.contrib.admindocs.urls')),

    # Uncomment the next line to enable the admin:
    # (r'^admin/', include(admin.site.urls)),
)
