<?php

use \RANDF\Core as Core;
use \Respect\Validation\Validator as v;

$app->map('/v1/events/:eventUUID', function($eventUUID) use ($app, $registry) {
    // Updates existing event
    $evt = new \RANDF\PEI\Data\Events();

    try {
        $eventID = $registry->UUID->getEntityReference('Events', $eventUUID);
        $eventIDPair = $registry->UUID->getByID($eventID);

        $req = $app->request();
        $event = $evt::sanitize($req->post());
        $event['event_id'] = $eventID;
        if (isset($event['associated_event_id'])) {
            $event['associated_event_id'] = $registry->UUID->getEntityReference('Events', $event['associated_event_id']);
        }
        if (isset($event['location_id'])) {
            $event['location_id'] = $registry->UUID->getEntityReference('Locations', $event['location_id']);
        }
        if (isset($event['contact_id'])) {
            $event['contact_id'] = $registry->UUID->getEntityReference('Contacts', $event['contact_id']);
        }

        $dbEvent = $evt::update($registry->UserID, $event);
        
        $eventIDPair = $registry->UUID->getByID($dbEvent['event_id']);
        $dbEvent['event_id'] = $eventIDPair['UUID'];
        if (isset($dbEvent['associated_event_id'])) {
            $associatedEventIDPair = $registry->UUID->getByID($dbEvent['associated_event_id']);
            $dbEvent['associated_event_id'] = $associatedEventIDPair['UUID'];
        }
        if (isset($dbEvent['location_id'])) {
            $locationIDPair = $registry->UUID->getByID($dbEvent['location_id']);
            $dbEvent['location_id'] = $locationIDPair['UUID'];
        }
        if (isset($dbEvent['contact_id'])) {
            $contactIDPair = $registry->UUID->getByID($dbEvent['contact_id']);
            $dbEvent['contact_id'] = $contactIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbEvent);
})->via('POST', 'PUT');

$app->delete('/v1/events/:eventUUID', function($eventUUID) use ($app, $registry) {
    // Delete existing event
    if ($eventUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $evt = new \RANDF\PEI\Data\Events();

    try {
        $eventIDPair = $registry->UUID->getByUUID($eventUUID);
        if ($eventIDPair === null) {
            $isNew = true;
            if ($app->request()->isPut()) {
                $eventIDPair = $registry->UUID->generateID($eventUUID);
            } else {
                $app->halt(404);
            }
        } else {
            $isNew = false;
        }
        $eventID = $eventIDPair['ID'];

        $success = $evt::delete($eventID);
        if ($success) {
            $registry->UUID->removeUUID($eventUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/events/:eventUUID/invitees/:inviteeUUID', function($eventUUID, $inviteeUUID) use ($app, $registry) {
    // Update an existing invitee
    $evt = new \RANDF\PEI\Data\Events();

    try {
        $eventIDPair = $registry->UUID->getByUUID($eventUUID);
        if ($eventIDPair === null) {
            if ($app->request()->isPut()) {
                $eventIDPair = $registry->UUID->generateID($eventUUID);
            } else {
                $app->halt(404);
            }
        }
        $eventID = $eventIDPair['ID'];

        $inviteeID = $registry->UUID->getEntityReference('Invitees', $inviteeUUID);
        $inviteeIDPair = $registry->UUID->getByID($inviteeID);
        
        $req = $app->request();
        $invitee = $evt::sanitizeInvitee($req->post());
        $invitee['event_id'] = $eventID;

        if (isset($invitee['contact_id'])) {
            $invitee['contact_id'] = $registry->UUID->getEntityReference('Contacts', $invitee['contact_id']);
        }
            
        $dbInvitee = $evt::updateInvitee($inviteeID, $invitee);
        
        $dbInvitee['invitee_id'] = $inviteeIDPair['UUID'];
        $dbInvitee['event_id'] = $eventIDPair['UUID'];
        
        if (isset($dbInvitee['contact_id'])) {
            $contactIDPair = $registry->UUID->getByID($dbInvitee['contact_id']);
            $dbInvitee['contact_id'] = $contactIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage() . "\n\n" . $e->getTraceAsString());
        //$app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbInvitee);
})->via('POST', 'PUT');

$app->delete('/v1/events/:eventUUID/invitees/:inviteeUUID', function($eventUUID, $inviteeUUID) use ($app, $registry) {
    // Delete existing invitee
    if ($eventUUID === '(null)' || $inviteeUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $evt = new \RANDF\PEI\Data\Events();

    try {
        $eventIDPair = $registry->UUID->getByUUID($eventUUID);
        if ($eventIDPair === null) {
            if ($app->request()->isPut()) {
                $eventIDPair = $registry->UUID->generateID($eventUUID);
            } else {
                $app->halt(404);
            }
        }
        $eventID = $eventIDPair['ID'];

        $inviteeIDPair = $registry->UUID->getByUUID($inviteeUUID);
        if ($inviteeIDPair === null) {
            if ($app->request()->isPut()) {
                $inviteeIDPair = $registry->UUID->generateID($inviteeUUID);
            } else {
                $app->halt(404);
            }
        }
        $inviteeID = $inviteeIDPair['ID'];

        $invitee = $evt::getInvitee($inviteeID);
        if ($invitee == false || $invitee['event_id'] != $eventID) {
            $app->halt(404);
        }

        $success = $evt::deleteInvitee($inviteeID);
        if ($success) {
            $registry->UUID->removeUUID($inviteeUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/locations/:locationUUID', function($locationUUID) use ($app, $registry) {
    // Updates existing location
    $loc = new \RANDF\PEI\Data\Locations();

    try {
        $locationID = $registry->UUID->getEntityReference('Locations', $locationUUID);
        $locationIDPair = $registry->UUID->getByID($locationID);

        $req = $app->request();
        $location = $loc::sanitize($req->post());
        $location['location_id'] = $locationID;
        
        $dbLocation = $loc::update($registry->UserID, $location);
        
        $location['location_id'] = $locationUUID;
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbLocation);
})->via('POST', 'PUT');

$app->delete('/v1/locations/:locationUUID', function($locationUUID) use ($app, $registry) {
    if ($locationUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    // Delete existing location
    $loc = new \RANDF\PEI\Data\Locations();

    try {
        $locationIDPair = $registry->UUID->getByUUID($locationUUID);
        if ($locationIDPair === null) {
            $app->halt(404);
        }
        $locationID = $locationIDPair['ID'];

        $success = $loc::delete($locationID);
        if ($success) {
            $registry->UUID->removeUUID($locationUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/contacts/:contactUUID', function($contactUUID) use ($app, $registry) {
    // Creates and updates existing contacts
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contactID = $registry->UUID->getEntityReference('Contacts', $contactUUID);
        $contactIDPair = $registry->UUID->getByID($contactID);
        
        $req = $app->request();
        $contact = $con::sanitize($req->post());
        $contact['contact_id'] = $contactID;
        if (isset($contact['location_id'])) {
            $contact['location_id'] = $registry->UUID->getEntityReference('Locations', $contact['location_id']);
        }
        if (isset($contact['current_status_id'])) {
            $contact['current_status_id'] = $registry->UUID->getEntityReference('Statuses', $contact['current_status_id']);
        }
        
        $dbContact = $con::update($registry->UserID, $contact);
        
        $dbContact['contact_id'] = $contactIDPair['UUID'];
        if (isset($dbContact['location_id'])) {
            $locationIDPair = $registry->UUID->getByID($dbContact['location_id']);
            $dbContact['location_id'] = $locationIDPair['UUID'];
        }
        if (isset($dbContact['current_status_id'])) {
            $statusIDPair = $registry->UUID->getByID($dbContact['current_status_id']);
            $dbContact['current_status_id'] = $statusIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbContact);
})->via('POST', 'PUT');

$app->delete('/v1/contacts/:contactUUID', function($contactUUID) use ($app, $registry) {
    if ($contactUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    if ($registry->PulseVersion == '0.02' || $registry->PulseVersion == '0.03' || $registry->PulseVersion == '0.04' || $registry->PulseVersion == '0.05') {
        $app->halt(204, 'Untrustworthy client');
    }
    // Deletes existing contact
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contactIDPair = $registry->UUID->getByUUID($contactUUID);
        if ($contactIDPair === null) {
            $app->halt(404);
        }
        $contactID = $contactIDPair['ID'];

        $success = $con::delete($contactID);
        if ($success) {
            $registry->UUID->removeUUID($contactUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/contacts/:contactUUID/statuses/:statusUUID', function($contactUUID, $statusUUID) use ($app, $registry) {
    // Creates and updates contact statuses
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contactIDPair = $registry->UUID->getByUUID($contactUUID);
        if ($contactIDPair === null) {
            $app->halt(404);
        }
        $contactID = $contactIDPair['ID'];
        
        $statusID = $registry->UUID->getEntityReference('Statuses', $statusUUID);
        $statusIDPair = $registry->UUID->getByID($statusID);
        
        $req = $app->request();
        $status = $con::sanitizeStatus($req->post());
        $status['contact_id'] = $contactID;
        $status['status_id'] = $statusID;
        
        $dbStatus = $con::updateStatus($status);
        $dbStatus['status_id'] = $statusIDPair['UUID'];
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbStatus);
})->via('POST', 'PUT');

$app->delete('/v1/contacts/:contactUUID/statuses/:statusUUID', function($contactUUID, $statusUUID) use ($app, $registry) {
    if ($contactUUID === '(null)' || $statusUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    // Deletes existing contact status
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contactIDPair = $registry->UUID->getByUUID($contactUUID);
        if ($contactIDPair === null) {
            $app->halt(404);
        }
        $contactID = $contactIDPair['ID'];
        
        $statusIDPair = $registry->UUID->getByUUID($statusUUID);
        if ($statusIDPair === null) {
            $app->halt(404);
        }
        $statusID = $statusIDPair['ID'];

        $success = $con::deleteStatus($statusID);
        if ($success) {
            $registry->UUID->removeUUID($statusUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/reminders/:reminderUUID', function($reminderUUID) use ($app, $registry) {
    // Create or update existing reminder
    $rem = new \RANDF\PEI\Data\Reminders();
    $evt = new \RANDF\PEI\Data\Events();

    try {
        $reminderID = $registry->UUID->getEntityReference('Reminders', $reminderUUID);
        $reminderIDPair = $registry->UUID->getByID($reminderID);
        
        $req = $app->request();
        $reminder = $rem::sanitize($req->post());
        
        if (isset($reminder['event_id'])) {
            $reminder['event_id'] = $registry->UUID->getEntityReference('Events', $reminder['event_id']);
        }
        
        $reminder['reminder_id'] = $reminderID;
        $dbReminder = $rem::update($registry->UserID, $reminder);
        
        $reminderIDPair = $registry->UUID->getByID($dbReminder['reminder_id']);
        $dbReminder['reminder_id'] = $reminderIDPair['UUID'];
        if (isset($dbReminder['event_id'])) {
            $eventIDPair = $registry->UUID->getByID($dbReminder['event_id']);
            $dbReminder['event_id'] = $eventIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($reminder);
})->via('POST', 'PUT');

$app->delete('/v1/reminders/:reminderUUID', function($reminderUUID) use ($app, $registry) {
    // Deletes existing reminder
    if ($reminderUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $rem = new \RANDF\PEI\Data\Reminders();

    try {
        $reminderIDPair = $registry->UUID->getByUUID($reminderUUID);
        if ($reminderIDPair === null) {
            $isNew = true;
            if ($app->request()->isPut()) {
                $reminderIDPair = $registry->UUID->generateID($reminderUUID);
            } else {
                $app->halt(404);
            }
        } else {
            $isNew = false;
        }
        $reminderID = $reminderIDPair['ID'];

        $success = $rem::delete($reminderID);
        if ($success) {
            $registry->UUID->removeUUID($reminderUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->post('/v1/notifications/:notificationUUID', function($notificationUUID) use ($app, $registry) {
    // Updates existing notification (setting read status)
    $not = new \RANDF\PEI\Data\Notifications();

    try {
        $notificationIDPair = $registry->UUID->getByUUID($notificationUUID);
        if ($notificationIDPair === null) {
            if ($app->request()->isPut()) {
                $notificationIDPair = $registry->UUID->generateID($notificationUUID);
            } else {
                $app->halt(404);
            }
        }
        $notificationID = $notificationIDPair['ID'];

        $req = $app->request();
        $is_read = $req->post('is_read');
        $not::markReadStatus($notificationID, $is_read);

        $notifications = $not::getAll($registry->UserID);
        foreach ($notifications as &$notification) {
            if ($notification['notification_id'] == $notificationID) {
                $notificationIDPair = $registry->UUID->getByID($notification['notification_id']);
                $notification['notification_id'] = $notificationIDPair['UUID'];
                break;
            }
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($notification);
});

$app->delete('/v1/notifications/:notificationUUID', function($notificationUUID) use ($app, $registry) {
    // Delete existing notification
    if ($notificationUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $not = new \RANDF\PEI\Data\Notifications();

    try {
        $notificationIDPair = $registry->UUID->getByUUID($notificationUUID);
        if ($notificationIDPair === null) {
            if ($app->request()->isPut()) {
                $notificationIDPair = $registry->UUID->generateID($notificationUUID);
            } else {
                $app->halt(404);
            }
        }
        $notificationID = $notificationIDPair['ID'];

        $success = $not::delete($notificationID);
        if ($success) {
            $registry->UUID->removeUUID($notificationUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/interactions/:interactionUUID', function($interactionUUID) use ($app, $registry) {
    // Creates/updates existing interactions
    $int = new \RANDF\PEI\Data\Interactions();
    $con = new \RANDF\PEI\Data\Contacts();

    $req = $app->request();

    try {
        $interactionID = $registry->UUID->getEntityReference('Interactions', $interactionUUID);
        $interactionIDPair = $registry->UUID->getByID($interactionID);
        
        $interaction = $int::sanitize($req->post());
        $interaction['interaction_id'] = $interactionID;
        
        if (isset($interaction['location_id'])) {
            $interaction['location_id'] = $registry->UUID->getEntityReference('Locations', $interaction['location_id']);
        }
        if (isset($interaction['template_id'])) {
            $interaction['template_id'] = $registry->UUID->getEntityReference('Templates', $interaction['template_id']);
        }
        if (isset($interaction['event_id'])) {
            $interaction['event_id'] = $registry->UUID->getEntityReference('Events', $interaction['event_id']);
        }
        if (isset($interaction['contact_id'])) {
            $interaction['contact_id'] = $registry->UUID->getEntityReference('Contacts', $interaction['contact_id']);
        }
        $dbInteraction = $int::update($registry->UserID, $interaction);
        
        if (!is_array($dbInteraction)) {
            $app->halt(404);
        }
        
        if (isset($dbInteraction['location_id'])) {
            $locationIDPair = $registry->UUID->getByID($dbInteraction['location_id']);
            $dbInteraction['location_id'] = $locationIDPair['UUID'];
        }
        if (isset($dbInteraction['template_id'])) {
            $templateIDPair = $registry->UUID->getByID($dbInteraction['template_id']);
            $dbInteraction['template_id'] = $templateIDPair['UUID'];
        }
        if (isset($dbInteraction['event_id'])) {
            $eventIDPair = $registry->UUID->getByID($dbInteraction['event_id']);
            $dbInteraction['event_id'] = $eventIDPair['UUID'];
        }
        $dbInteraction['interaction_id'] = $interactionIDPair['UUID'];
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbInteraction);
})->via('POST', 'PUT');

$app->delete('/v1/interactions/:interactionUUID', function($interactionUUID) use ($app, $registry) {
    // Deletes existing interaction
    if ($interactionUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $int = new \RANDF\PEI\Data\Interactions();

    try {
        $interactionIDPair = $registry->UUID->getByUUID($interactionUUID);
        if ($interactionIDPair === null) {
            $app->halt(404);
        }
        $interactionID = $interactionIDPair['ID'];
        
        $success = $int::delete($interactionID);
        if ($success) {
            $registry->UUID->removeUUID($interactionUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/templates/:templateUUID', function($templateUUID) use ($app, $registry) {
    // Updates existing template
    $tpl = new \RANDF\PEI\Data\Templates();

    try {
        $templateID = $registry->UUID->getEntityReference('Templates', $templateUUID);
        $templateIDPair = $registry->UUID->getByID($templateID);
        
        $req = $app->request();
        $template = $tpl::sanitize($req->post());
        $template['user_id'] = $registry->UserID;
        
        $template['template_id'] = $templateID;
        $dbTemplate = $tpl::update($registry->UserID, $template);
        $dbTemplate['template_id'] = $templateIDPair['UUID'];
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbTemplate);
})->via('POST', 'PUT');

$app->delete('/v1/templates/:templateUUID', function($templateUUID) use ($app, $registry) {
    // Deletes existing template
    if ($templateUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $tpl = new \RANDF\PEI\Data\Templates();

    try {
        $templateIDPair = $registry->UUID->getByUUID($templateUUID);
        if ($templateIDPair === null) {
            $app->halt(404);
        }
        $templateID = $templateIDPair['ID'];

        $success = $tpl::delete($templateID);
        if ($success) {
            $registry->UUID->removeUUID($templateUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->map('/v1/recordings/:recordingUUID', function($recordingUUID) use ($app, $registry) {
    // Creates or updates existing recording
    $rec = new \RANDF\PEI\Data\Recordings();

    try {
        $recordingID = $registry->UUID->getEntityReference('Recordings', $recordingUUID);
        $recordingIDPair = $registry->UUID->getByID($recordingID);
        
        $req = $app->request();
        $recording = $rec::sanitize($req->post());
        $recording['user_id'] = $registry->UserID;
        if (isset($recording['contact_id'])) {
            $recording['contact_id'] = $registry->UUID->getEntityReference('Contacts', $recording['contact_id']);
        }
        
        $recording['recording_id'] = $recordingID;
        $dbRecording = $rec::update($registry->UserID, $recording);
        $dbRecording['recording_id'] = $recordingUUID;
        if (isset($dbRecording['contact_id'])) {
            $contactIDPair = $registry->UUID->getByID($dbRecording['contact_id']);
            $dbRecording['contact_id'] = $contactIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbRecording);
})->via('POST', 'PUT');

$app->delete('/v1/recordings/:recordingUUID', function($recordingUUID) use ($app, $registry) {
    // Deletes existing recording
    if ($recordingUUID === '(null)') {
        $app->halt(204, 'Null request');
    }
    $rec = new \RANDF\PEI\Data\Recordings();

    try {
        $recordingIDPair = $registry->UUID->getByUUID($recordingUUID);
        if ($recordingUUID === null) {
            $app->halt(404);
        }
        $recordingID = $recordingIDPair['ID'];

        $success = $rec::delete($recordingID);
        if ($success) {
            $registry->UUID->removeUUID($recordingUUID);
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    if ($success) {
        $app->halt(204);
    } else {
        $app->halt(403);
    }
});

$app->post('/v1/notifications/share/', function() use ($app, $registry) {
    // Records a "sharing" event from a consultant to their sponsor;
    // - records the sharing event (where??)
    // - creates a new notification for the sponsor letting them know it's happened.
    
    $req = $app->request();
    $not = new \RANDF\PEI\Data\Notifications();
    $auth = new \RANDF\PEI\Authentication();

    try {
        $results = array(
            'success' => $not::createSharingNotification($registry->UserID, $auth::getUserIdFromAccountId($req->post('recipient_account_id')), $req->post('share_type')),
            );
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($results);
});

$app->post('/v1/notifications/kudos/', function() use ($app, $registry) {
    // Records a "sharing" event from a consultant to their sponsor;
    // - records the sharing event (where??)
    // - creates a new notification for the sponsor letting them know it's happened.
    
    $req = $app->request();
    $not = new \RANDF\PEI\Data\Notifications();
    $auth = new \RANDF\PEI\Authentication();

    try {
        $results = array(
            'success' => $not::createKudosNotification($registry->UserID, $auth::getUserIdFromAccountId($req->post('recipient_account_id')), $req->post('kudos')),
            );
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($results);
});

$app->map('/v1/api/settings/', function() use ($app, $registry) {
    // Sets & gets the opt-out status and user settings for the active consultant
    
    $req = $app->request();
    $stats = new \RANDF\PEI\Data\Stats();
    $settings = new \RANDF\PEI\Data\Settings();
    
    try {
        if ($req->isPost()) {
            $optout = $req->post('share_progress_with_sponsor');
            if (isset($optout)) {
                $stats::setOptOutStatus($registry->UserID, $optout);
            }
            
            $receive_progress_notifications = $req->post('receive_progress_notifications');
            $notify_about_upcoming_business_meetings = $req->post('notify_about_upcoming_business_meetings');
            $notify_about_upcoming_corporate_events = $req->post('notify_about_upcoming_corporate_events');
            
            $settings::setUserSettings($registry->UserID, array(
                'receive_progress_notifications' => $receive_progress_notifications,
                'notify_about_upcoming_business_meetings' => $notify_about_upcoming_business_meetings,
                'notify_about_upcoming_corporate_events' => $notify_about_upcoming_corporate_events,
                ));
        }
        
        $results = $settings::getUserSettings($registry->UserID);
        $results['share_progress_with_sponsor'] = (bool)$stats::getOptOutStatus($registry->UserID);
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }
    
    echo Core::json_encode($results);
})->via('GET', 'POST');

$app->put('/v1/sharing/', function() use ($app, $registry) {
    // Records a new sharing event
    
    $req = $app->request();
    $shar = new \RANDF\PEI\Data\Sharing();
    
    try {
        $sharing = $shar::sanitize($req->post());
        $sharing['user_id'] = $registry->UserID;
        
        $uuid = $registry->UUID->createNewUUID();
        $sharingIDPair = $registry->UUID->generateID($uuid);
        $sharing['sharing_id'] = $sharingIDPair['ID'];
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }
});
