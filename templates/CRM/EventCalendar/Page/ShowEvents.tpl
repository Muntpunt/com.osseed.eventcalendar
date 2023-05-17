<div class='fc-toolbar fc-header-toolbar'>
{if $eventTypes == TRUE}
  <div class=" fc-left">
  <label for="event_selector">{ts}Event Type{/ts}</label>
    <select id="event_selector" class="crm-form-select crm-select2 crm-action-menu fa-plu" multiple="multiple">
    <option value="all">{ts}All{/ts}</option>
    {foreach from=$eventTypes item=type}
         <option value="{$type}">{$type}</option>
    {/foreach}
  </select>
  </div>
{/if}
  <div class="fc-center">
  <label for="event_room">{ts}Zaal{/ts}</label>
  <select class="crm-form-select crm-select2 crm-action-menu" id="event_room" multiple="multiple">
      <option value="all">{ts}All{/ts}</option>
      {foreach from=$rooms key=key item=room}
        <option value="{$key}">{$room}</option>
      {/foreach}
  </select>
  </div>
  <div class="event-title-search fc-right" id="civievents-calendar-titles">
    <label for="event_title">{ts}Title{/ts}</label>
    <input id="event_title" class="crm-form-text" type="text" name="event_title"/>
  </div>
  </div>
  <div class="fc-clear"></div>

</div>

<div id="calendar"></div>
{literal}
<script type="text/javascript">
  if (typeof(jQuery) != 'function'){
    var jQuery = cj;
  }
  else {
    var cj = jQuery;
  }

  cj( function( ) {
    checkFullCalendarLIbrary()
    .then(function() {
      buildCalendar();
    })
    .catch(function() {
      alert('Error loading calendar, try refreshing...');
    });
  });

/*
 * Checks if full calendar API is ready.
 *
 * @returns {Promise}
 *  if library is available or not.
 */
function checkFullCalendarLIbrary() {
  return new Promise((resolve, reject) => {
    if(cj.fullCalendar) {
      resolve();
    } else {
      cj(document).ajaxComplete(function() {
        if(cj.fullCalendar) {
          resolve();
        } else {
          reject();
        }
      });
    }
  });
}

function buildCalendar( ) {
  var events_data = {/literal}{$civicrm_events}{literal};
  var jsonStr = JSON.stringify(events_data);
  var showTime = events_data.timeDisplay;
  var weekStartDay = {/literal}{$weekBeginDay}{literal};

  cj('#calendar').fullCalendar({
    eventSources: [
      { events: events_data.events,}
    ],
    displayEventEnd: true,
    displayEventTime: showTime ? 1 : 0,
    firstDay:weekStartDay,
    timeFormat: 'H:mm',
    header: {
      left: 'prev,next today',
      center: 'title',
      right: 'month,agendaWeek,agendaDay'
    },

    eventRender: function eventRender( event, element, view ) {
      element.append(event.location);
      show = true;
      eventTypes = cj('#event_selector').val();
      if(eventTypes) {
        show = show && eventTypes.includes(event.eventType);
      }
      selectedTitle = cj('#event_title').val();
      if(selectedTitle.length>0){
        show = show && (event.title.toLowerCase().includes(selectedTitle.toLowerCase()));
      }
      eventRooms = cj('#event_room').val();
      if(eventRooms){
        show = show && ((Object.values(event.rooms).filter(value => eventRooms.includes(value))).length > 0);
      }
      return show;
    },

    eventAfterRender: function eventRender( event, element, view ) {
      element.attr('target','_blank');
    }
  });

  CRM.$(function($) {
    $("#event_selector").select2().change(function() {
      cj('#calendar').fullCalendar('rerenderEvents');
    });
    $("#event_title").on("input",function() {
      cj('#calendar').fullCalendar('rerenderEvents');
    });
    $("#event_room").select2().change(function() {
      cj('#calendar').fullCalendar('rerenderEvents');
    });
  });
}
</script>
{/literal}
