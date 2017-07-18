
/**
 ** Used for date printing
 */
function GcDatePrinter() {
  var days = {
    0: 'Sunday',
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday'
  };
  var months = {
    0: 'January',
    1: 'February',
    2: 'March',
    3: 'April',
    4: 'May',
    5: 'June',
    6: 'July',
    7: 'August',
    8: 'September',
    9: 'October',
    10: 'November',
    11: 'December'
  };

  this.formatDate = function (d) {
    return days[d.getDay()] + ' ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear()
        + ' ' + d.getHours() + ':' + d.getMinutes() + ':' + d.getSeconds();
  };

  this.formatToday = function () {
    var d = new Date();
    return this.formatDate(d);
  };
}


jQuery(function ($) {
  'use strict';

  var $gc_activated = $('#graphcomment_activated_checkbox');
  var $gc_activated_all = $('input[name="gc_activated_all"]');
  var $gc_sync_comments = $('#graphcomment_sync_comments_checkbox');
  var $datepicker = $('#datepicker');
  var $submenu_notif = $('#submenu_notif_new_comments');

  var tabs = [
    {
      data_toggle: 'general',
      tab: $('#graphcomment-options-general-tab'),
      content: $('#graphcomment-general')
    },
    {
      data_toggle: 'synchronization',
      tab: $('#graphcomment-options-synchronization-tab'),
      content: $('#graphcomment-synchronization')
    },
    {
      data_toggle: 'importation',
      tab: $('#graphcomment-options-importation-tab'),
      content: $('#graphcomment-importation')
    },
    {
      data_toggle: 'notification',
      tab: $('#graphcomment-options-notification-tab'),
      content: $('#graphcomment-notification')
    }
  ];

  $datepicker.datepicker({dateFormat: 'yy-mm-dd'});

  function functionHandleTabClick() {
    if (!$(this).hasClass('active')) {
      var toggleContent = $(this).attr('data-toggle');

      tabs.forEach(function (t) {
        // Search for the active tab
        if (t.tab.hasClass('active')) {
          // Deactivate it
          t.tab.removeClass('active');
          t.content.fadeOut(250, function () {
            t.content.removeClass('active');
            // Search for the tab to activate
            tabs.every(function (t_toggle) {
              if (t_toggle.data_toggle === toggleContent) {
                t_toggle.tab.addClass('active');
                t_toggle.content.fadeIn(250);
                t_toggle.content.addClass('active');
                return false;
              }
              return true;
            });
          });
          return false;
        }
        return true;
      });
    }
  }

  // Init the action for the tabs clicks
  tabs.forEach(function (t) {
    t.tab.click(functionHandleTabClick);
  });

  $gc_activated.change(function () {
    var activated = $(this).is(':checked');

    $datepicker.prop('disabled', !activated);

    $gc_activated_all.prop('disabled', !activated);

    $gc_sync_comments.prop('disabled', !activated);

    if (!activated) {
      $gc_activated_all.prop('checked', false);
      $gc_sync_comments.prop('checked', false);
    }
  });

  $gc_activated_all.change(function () {
    var activated = $(this).is(':checked');
    $datepicker.prop('disabled', activated)
  });

  $('#graphcomment-delete-notification').click(function () {
    $('<input>').attr({
      type: 'hidden',
      name: 'gc-delete-notification',
      value: 'true'
    }).appendTo('#graphcomment-options-form-notif');
  });


  $('#graphcomment-change-website').click(function () {
    $('<input>').attr({
      type: 'hidden',
      name: 'gc-change-website',
      value: 'true'
    }).appendTo('#graphcomment-options-form-general');
  });

  $('#graphcomment-disconnect-button').click(function() {
    window.location.href = window.location.href.replace(/#$/, '').replace('page=graphcomment', 'page=settings') + '&graphcomment-disconnect=true';
  });

  var graphcomment_tabs_alerts_sync = $('.graphcomment-tabs-alerts-sync');
  var graphcomment_tabs_alerts_sync_interval;
  if (graphcomment_tabs_alerts_sync) {
    graphcomment_tabs_alerts_sync_interval = setInterval(function () {
      graphcomment_tabs_alerts_sync.toggle();
    }, 750);
  }

  var graphcomment_tabs_alerts_notif = $('.graphcomment-tabs-alerts-notif');
  var graphcomment_tabs_alerts_notif_interval;
  if (graphcomment_tabs_alerts_notif) {
    graphcomment_tabs_alerts_notif_interval = setInterval(function () {
      graphcomment_tabs_alerts_notif.toggle();
    }, 750);
  }

  if (typeof gc_logout !== 'undefined' && gc_logout === true) {
    setTimeout(function() {
      window.location.reload();
    }, 1000);
  }

  // Tooltip init
  $('[data-toggle="tooltip"]').tooltip();

  // Handle the notif deletion
  $('#graphcomment-notif-delete').click(function(e) {
    console.log('deleting the notification');

    var data = {
      'action': 'graphcomment_notif_delete'
    };

    $.post(ajaxurl, data, function (response) {
      console.log('graphcomment-settings-notifs success');
      $('.graphcomment-settings-notifs').empty().removeClass();
      $submenu_notif.hide();
    }, function(err) {
      console.log('graphcomment-settings-notifs error', err);
    });
  });

  // Handle the notif redirection to admin
  $('#graphcomment-notif-go-admin').click(function() {
    document.location.href = document.location.href.replace('settings', 'graphcomment');
  });

  $('#graphcomment-create-website').click(function(e) {
    e.preventDefault();
    $('<input>').attr({
      type: 'hidden',
      name: 'gc-create-website',
      value: 'true'
    }).appendTo('#graphcomment-create-website-form');
    $('#gc_select_website_submit_button').trigger('click');
  });

  window.onCreateWebsite = function() {
    window.location.href = window.location.href.replace('page=graphcomment', 'page=settings');
  };

  var import_label_value_obj = $('.gc-label-status-value');
  var import_status_hidden = $('input[name=gc-import-status]');
  var progress_bar_obj = $('.progress-bar');
  var nbr_imported_comment_obj = $('.gc-import-nbr-comment-import');
  var import_pannel_obj = $('#graphcomment-import-pannel');

  if (import_status_hidden.attr('value') === 'pending') {

    var intervalId = setInterval(function () {

      var data = {
        'action': 'graphcomment_import_pending_get_advancement'
      };

      // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
      $.post(ajaxurl, data, function (response) {
        response = JSON.parse(response);

        if (response.status === false || import_status_hidden.attr('value') !== 'pending') {
          clearInterval(intervalId);
          return;
        }

        import_label_value_obj.html(response.status.toUpperCase());
        import_status_hidden.attr('value', response.status);

        progress_bar_obj.attr('aria-valuenow', response.nbr_imported_comment);
        progress_bar_obj.attr('style', 'width:' + response.percent + '%');
        progress_bar_obj.html(Math.trunc(response.percent) + '%');

        nbr_imported_comment_obj.html(response.nbr_comment_import);

        if (response.status === 'finished') {
          import_label_value_obj.removeClass();
          import_label_value_obj.addClass('gc-label-status-value label label-pill label-success');
          progress_bar_obj.removeClass();
          progress_bar_obj.addClass('progress-bar progress-bar-success progress-bar-success');
          import_pannel_obj.removeClass();
          import_pannel_obj.addClass('panel panel-success');

          $('.gc-import-finished-date').html((new GcDatePrinter()).formatToday());
          // Remove the class that hide it -> Print the element
          $('.gc-import-finished-date-hide').removeClass();

          $('.gc-import-pending-stop').remove();
          $('.gc-import-pending-stop-input').remove();
        }
        else if (response.status === 'error') {
          import_label_value_obj.removeClass();
          import_label_value_obj.addClass('gc-label-status-value label label-pill label-danger');
          progress_bar_obj.removeClass();
          progress_bar_obj.addClass('progress-bar progress-bar-success progress-bar-danger');
          import_pannel_obj.removeClass();
          import_pannel_obj.addClass('panel panel-danger');

          $('<input>').attr({
            type: 'hidden',
            name: 'gc-import-restart',
            value: 'true'
          }).appendTo('#graphcomment-options-form-import');

          $('.gc-import-error-hide').removeClass();

          $('.gc-import-pending-stop').remove();
          $('.gc-import-pending-stop-input').remove();
        }
      });

    }, 1000);
  }

  $('.gc-import-pending-stop').click(function(e) {
    $('<input>').attr({
      type: 'hidden',
      name: 'gc-import-stop',
      value: 'true'
    }).appendTo('#graphcomment-options-form-import');
  });


  function graphCommentAuthSuccess() {
    document.location.reload();
  }

  window.oauthPopupClose = function(timeout) {
    if (timeout === true) {
      setTimeout(function() {
        graphCommentAuthSuccess();
      }, 1500);
    }
    else {
      graphCommentAuthSuccess();
    }
  };

  var $connectionSuccessWrap = $('#connection_success_wrap');

  if ($connectionSuccessWrap) {
    $connectionSuccessWrap.find('a').click(graphCommentAuthSuccess);
  }
});
