var calendar;

$(document).ready(function() {
  const calendarEl = document.getElementById("calendar");
  calendar = new FullCalendar.Calendar(calendarEl, {
    themeSystem: 'bootstrap5',
    initialView: 'dayGridYear',
    events: '/api/events',
    selectable: true,
    select: cal_on_select,
    eventClick: cal_on_eventClick
  });

  $("#submit-btn").click(save_event);
  $("#delete-btn").click(delete_event);

  $("#event-modal").on('hide.bs.modal', reset_modal_form);

  $(".form-control").change(function() {
    $(this).removeClass('is-invalid');
  });

  calendar.render();

  setInterval( function() {
    calendar.refetchEvents();
  }, 10000);
});


function cal_on_select(info) {
  $('#input-start-date').val(info.startStr);
  const end_date_exclusive = dayjs(info.end);
  const end_date_str = end_date_exclusive.subtract(1, 'day').format('YYYY-MM-DD');
  $('#input-end-date').val(end_date_str);
  $('#event-modal').modal('show');        
}

function cal_on_eventClick(info) {
  const event = info.event;
  const end_date_exclusive = dayjs(event.end);
  const end_date_str = end_date_exclusive.subtract(1, 'day').format('YYYY-MM-DD');
  $('#event-id').val(event.id);
  $('#event-title').val(event.title);
  $('#input-start-date').val(event.startStr);
  $('#input-end-date').val(end_date_str);

  $('#delete-btn').removeClass('d-none');
  $('#event-modal').modal('show');
}

function save_event_on_ajax_error(xhr) {
  const error_obj = JSON.parse(xhr.responseText);
  console.log('save_event_on_ajax_error: ' + xhr.responseText);
  $(":button").attr("disabled", false);
  $("#submit-btn").html("Save changes");
  if (error_obj.overlap_found) {
    if (error_obj.input_start_date) {
      $("#input-start-date-msg").text(error_obj.input_start_date);
      $("#input-start-date").addClass('is-invalid');
    }
    if (error_obj.input_end_date) {
      $("#input-end-date-msg").text(error_obj.input_end_date);
      $("#input-end-date").addClass('is-invalid');
    }
  }
}

function save_event(e) {
  const form_data = new FormData(document.getElementById("add-event-form-modal"));
  const event = Object.fromEntries(form_data);
  event.end = dayjs(event.end).add(1, 'day').format('YYYY-MM-DD');

  event.title = 'title of event';
  console.log(event);

  $(":button").attr("disabled", true);
  $("#submit-btn").html("Saving ...");

  if (event.id > 0) {
    // update existing event

    $.ajax({
      url: '/api/events/' + event.id,
      dataType: 'json',
      type: 'put',
      data: JSON.stringify(event),
      success: function(xhr) {
        cal_event = calendar.getEventById(event.id);
        if (cal_event != null) {
          cal_event.setDates(event.start, event.end, {allDay: true});
        };
        $("#event-modal").modal('hide');
      },
      error: save_event_on_ajax_error
    })
  } else {
    // create new event

    $.ajax({
      url: '/api/events',
      data: JSON.stringify(event),
      dataType: 'json',
      type: 'post',
      success: function(event_created) {
        // second param TRUE selects first event source,
        // without this, we get duplicated events with refetchEvents()
        // see https://fullcalendar.io/docs/Calendar-addEvent
        calendar.addEvent(event_created, true);
        $("#event-modal").modal('hide');
      },
      error: save_event_on_ajax_error
    });
  }
}

function delete_event(e) {
  const form_data = new FormData(document.getElementById("add-event-form-modal"));
  const event = Object.fromEntries(form_data);
  event.end = dayjs(event.end).add(1, 'day').format('YYYY-MM-DD');
  console.log(event);

  $.ajax({
    url: '/api/events/' + event.id,
    dataType: 'json',
    type: 'delete',
    success: function() {
      cal_event = calendar.getEventById(event.id);
      if (cal_event != null) {
        cal_event.remove();
      }
      $("#event-modal").modal('hide');
    }
  })
}

function reset_modal_form() {
  $("#add-event-form-modal .is-invalid").removeClass('is-invalid');
  $('#delete-btn').addClass('d-none');
  $('#event-id').val('');
  $('#event-title').val('');
  $(":button").attr("disabled", false);
  $("#submit-btn").html("Save changes");
}



