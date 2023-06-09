<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2011
 * $Id$
 *
 */

require_once 'CRM/Core/Page.php';

class CRM_EventCalendar_Page_ShowEvents extends CRM_Core_Page {

  private function zalenOptionGroupId(){
    return civicrm_api3('CustomField', 'getvalue', [
      'return' => "option_group_id",
      'name' => "muntpunt_zalen",
    ]);
  }

  private function roomList($groupId){
     $result = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => $groupId,
      'options' => [
        'limit' => 0,
        'sort' => 'label'
      ]
     ]);
     $options = [];
     foreach($result['values'] as $value){
       $options[$value['value']] = $value['label'];
     }
     return $options;
  }

  public function run() {
    CRM_Core_Resources::singleton()->addScriptFile('com.osseed.eventcalendar', 'js/moment.js', 5);
    CRM_Core_Resources::singleton()->addScriptFile('com.osseed.eventcalendar', 'js/fullcalendar.js', 10);
    CRM_Core_Resources::singleton()->addScriptFile('com.osseed.eventcalendar', 'js/nl-be.js', 10);
    CRM_Core_Resources::singleton()->addStyleFile('com.osseed.eventcalendar', 'css/civicrm_events.css');
    CRM_Core_Resources::singleton()->addStyleFile('com.osseed.eventcalendar', 'css/fullcalendar.css');

    $eventTypesFilter = array();
    $civieventTypesList = CRM_Event_PseudoConstant::eventType();

    $config = CRM_Core_Config::singleton();
    //get settings
    $settings = $this->_eventCalendar_getSettings();
    //set title from settings; allow empty value so we don't duplicate titles
    CRM_Utils_System::setTitle(ts($settings['calendar_title']));

    $whereCondition = '';
    if (array_key_exists("event_types", $settings)) {
      $eventTypes = $settings['event_types'];
    }

    $calendarId = isset($_GET['id']) ? $_GET['id'] : '';
    if ($calendarId) {
      if (!empty($eventTypes)) {
        $eventTypesList = implode(',', array_keys($eventTypes));
        $whereCondition .= " AND civicrm_event.event_type_id in ({$eventTypesList})";
      }
      else {
        $whereCondition .= ' AND civicrm_event.event_type_id in (0)';
      }
    }

    //Show/Hide Past Events
    $currentDate = date("Y-m-d h:i:s", time() - (86400 * 60));
    if (empty($settings['event_past'])) {
      $whereCondition .= " AND civicrm_event.start_date > '" . $currentDate . "'";
    }

    // Show events according to number of next months
    if (!empty($settings['event_from_month'])) {
      $monthEvents = $settings['event_from_month'];
      $monthEventsDate = date("Y-m-d h:i:s",
        strtotime(date("Y-m-d h:i:s", strtotime($currentDate)) . "+" . $monthEvents . " month"));
      $whereCondition .= " AND civicrm_event.start_date < '" . $monthEventsDate . "'";
    }

    //Show/Hide Public Events
    if (!empty($settings['event_is_public'])) {
      $whereCondition .= " AND civicrm_event.is_public = 1";
    }

    //Check recurringEvent is available or not.
    if(isset($settings['recurring_event']) && $settings['recurring_event'] == 1) {
      $query = "
        SELECT `civicrm_recurring_entity`.`entity_id` id,
               `title` title,
               `start_date` start,
               `end_date` end ,
               `event_type_id` event_type,
               info.muntpunt_zalen rooms,
               ifnull(adr.name, concat(adr.street_address, ' :: ', adr.city)) location
        FROM `civicrm_event`
        LEFT JOIN civicrm_recurring_entity ON civicrm_recurring_entity.entity_id = civicrm_event.id
        LEFT JOIN civicrm_value_extra_evenement_info info ON (info.entity_id = civicrm_event.id)
        JOIN civicrm_loc_block lb ON (civicrm_event.loc_block_id = lb.id)
        JOIN civicrm_address adr ON (lb.address_id = adr.id)
        WHERE civicrm_recurring_entity.entity_table='civicrm_event'
          AND civicrm_event.is_active = 1
          AND civicrm_event.is_template = 0
      ";
    }
    else {
      $query = "
        SELECT `civicrm_event`.`id`,
               `title`,
               `start_date` start,
               `end_date` end ,
               `event_type_id` event_type,
               info.muntpunt_zalen rooms,
               ifnull(adr.name, concat(adr.street_address, ' :: ', adr.city)) location
        FROM `civicrm_event`
        LEFT JOIN civicrm_value_extra_evenement_info info ON (info.entity_id = civicrm_event.id)
        JOIN civicrm_loc_block lb ON (civicrm_event.loc_block_id = lb.id)
        JOIN civicrm_address adr ON (lb.address_id = adr.id)
        WHERE civicrm_event.is_active = 1
          AND civicrm_event.is_template = 0
      ";
    }

    $query .= $whereCondition;
    $events['events'] = array();

    $dao = CRM_Core_DAO::executeQuery($query);
    $eventCalendarParams = array('title' => 'title', 'start' => 'start', 'url' => 'url', 'location' => 'location', 'rooms'=>'rooms');

    if (!empty($settings['event_end_date'])) {
      $eventCalendarParams['end'] = 'end';
    }

    $roomList = $this->roomList($this->zalenOptionGroupId());

    while ($dao->fetch()) {
      $eventData = array();
      $dao->url = html_entity_decode(CRM_Utils_System::url('civicrm/event/manage/settings',[
         'reset' => 1,
         'action' => 'update',
         'id' =>  $dao->id]));
      foreach ($eventCalendarParams as $k) {
        $eventData[$k] = $dao->$k;
        if (!empty($eventTypes)) {
          $eventData['backgroundColor'] = "#{$eventTypes[$dao->event_type]}";
          $eventData['textColor'] = $this->_getContrastTextColor($eventData['backgroundColor']);
          $eventData['eventType'] = $civieventTypesList[$dao->event_type];
        }
        elseif ($calendarId == 0) {
          $eventData['backgroundColor'] = "";
          $eventData['textColor'] = $this->_getContrastTextColor($eventData['backgroundColor']);
          $eventData['eventType'] = $civieventTypesList[$dao->event_type];
        }
      }
      $rooms = array_filter(explode(CRM_Core_DAO::VALUE_SEPARATOR,$eventData['rooms']),
        function($x){ return !empty($x); }
      );
      $eventData['rooms'] = $rooms;
      if(!empty($rooms)){
        $eventData['location'] = implode(',',array_map(
          function($key) use ($roomList) {return $roomList[$key];},
          $rooms
        ));
      }

      $enrollment_status = civicrm_api3('Event', 'getsingle', [
        'return' => ['is_full'],
        'id' => $dao->id,
      ]);

      // Show/Hide enrollment status
      if(!empty($settings['enrollment_status'])) {
        if( !(isset($enrollment_status['is_error']))  && ( $enrollment_status['is_full'] == "1" ) ) {
          $eventData['title'] ='VOL ' .  $eventData['title'];
        }
      }
      $events['timeDisplay'] = !empty($settings['event_time']) ?: '';
      $events['isfilter'] = !empty($settings['event_event_type_filter']) ?: '';
      $events['events'][] = $eventData;
      $eventTypesFilter[$dao->event_type] = $civieventTypesList[$dao->event_type];
    }

    if(!empty($settings['event_event_type_filter'])) {
      $events['eventTypes'][]  = $eventTypesFilter;
      $this->assign('eventTypes', $eventTypesFilter);
    }

    $this->assign('rooms', $roomList);

    $events['displayEventEnd'] = 'true';

    //Check weekBegin settings from calendar configuration
    $weekBegins = '';
    if(isset($settings['week_begins_from_day']) && $settings['week_begins_from_day'] == 1) {
      //Get existing setting for weekday from civicrm start & set into event calendar.
      $weekBegins = Civi::settings()->get('weekBegins');
    }
    $weekBegins = $weekBegins ? $weekBegins : 0;
    $this->assign('weekBeginDay', $weekBegins);

    //Send Events array to calendar.
    $this->assign('civicrm_events', json_encode($events));
    parent::run();
  }

  /**
   * retrieve and reconstruct extension settings
   */
   public function _eventCalendar_getSettings() {
     $settings = array();
     $calendarId = isset($_GET['id']) ? $_GET['id'] : '';

     if ($calendarId) {
       $sql = "SELECT * FROM civicrm_event_calendar WHERE `id` = {$calendarId};";
       $dao = CRM_Core_DAO::executeQuery($sql);
       while ($dao->fetch()) {
         $settings['calendar_title'] = $dao->calendar_title;
         $settings['event_past'] = $dao->show_past_events;
         $settings['event_end_date'] = $dao->show_end_date;
         $settings['event_is_public'] = $dao->show_public_events;
         $settings['event_month'] = $dao->events_by_month;
         $settings['event_from_month'] = $dao->events_from_month;
         $settings['event_time'] = $dao->event_timings;
         $settings['event_event_type_filter'] = $dao->event_type_filters;
         $settings['week_begins_from_day'] = $dao->week_begins_from_day;
         $settings['recurring_event'] = $dao->recurring_event;
         $settings['enrollment_status'] = $dao->enrollment_status;
       }

       $sql = "SELECT * FROM civicrm_event_calendar_event_type WHERE `event_calendar_id` = {$calendarId};";
       $dao = CRM_Core_DAO::executeQuery($sql);
       $eventTypes = array();
       while ($dao->fetch()) {
         $eventTypes[] = $dao->toArray();
       }
    }
    elseif ($calendarId == 0) {
      $settings['calendar_title'] = 'Event Calendar';
      $settings['event_is_public'] = 1;
      $settings['event_past'] = 1;
      $settings['enrollment_status'] = 1;
    }

    if (!empty($eventTypes)) {
      foreach ($eventTypes as $eventType) {
        $settings['event_types'][$eventType['event_type']] = $eventType['event_color'];
      }
    }

    return $settings;
  }

  /*
   * Return contrast color on the basis the hex color passed
   *
   * Referred from https://stackoverflow.com/questions/1331591
   */
  function _getContrastTextColor($hexColor){
    // hexColor RGB
    $R1 = hexdec(substr($hexColor, 1, 2));
    $G1 = hexdec(substr($hexColor, 3, 2));
    $B1 = hexdec(substr($hexColor, 5, 2));

    // Black RGB
    $blackColor = "#000000";
    $R2BlackColor = hexdec(substr($blackColor, 1, 2));
    $G2BlackColor = hexdec(substr($blackColor, 3, 2));
    $B2BlackColor = hexdec(substr($blackColor, 5, 2));

    // Calc contrast ratio
    $L1 = 0.2126 * pow($R1 / 255, 2.2) +
          0.7152 * pow($G1 / 255, 2.2) +
          0.0722 * pow($B1 / 255, 2.2);

    $L2 = 0.2126 * pow($R2BlackColor / 255, 2.2) +
          0.7152 * pow($G2BlackColor / 255, 2.2) +
          0.0722 * pow($B2BlackColor / 255, 2.2);

    $contrastRatio = 0;
    if ($L1 > $L2) {
      $contrastRatio = (int)(($L1 + 0.05) / ($L2 + 0.05));
    } else {
      $contrastRatio = (int)(($L2 + 0.05) / ($L1 + 0.05));
    }

    // If contrast is more than 5, return black color
    if ($contrastRatio > 5) {
      return '#000000';
    } else {
      // if not, return white color.
      return '#FFFFFF';
    }
  }
}
