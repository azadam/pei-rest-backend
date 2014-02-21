<?php

use \RANDF\Core as Core;
use \Respect\Validation\Validator as v;

$app->get('/v1/events/', function() use ($app, $registry) {
   // Gets all of the current user's events
    $evt = new \RANDF\PEI\Data\Events();

    try {
        $events = $evt::getAll($registry->UserID);
        foreach ($events as &$event) {
            $eventIDPair = $registry->UUID->getByID($event['event_id']);
            $event['event_id'] = $eventIDPair['UUID'];
            if (isset($event['associated_event_id'])) {
                $eventIDPair = $registry->UUID->getByID($event['associated_event_id']);
                $event['associated_event_id'] = $eventIDPair['UUID'];
            }
            if (isset($event['location_id'])) {
                $locationIDPair = $registry->UUID->getByID($event['location_id']);
                $event['location_id'] = $locationIDPair['UUID'];
            }
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($events);
});

$app->get('/v1/events/:eventUUID', function($eventUUID) use ($app, $registry) {
    // Retrieves existing event
      
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
        
        $event = $evt::get($eventID);
        $eventIDPair = $registry->UUID->getByID($event['event_id']);
        $event['event_id'] = $eventIDPair['UUID'];
        if (isset($event['associated_event_id'])) {
            $eventIDPair = $registry->UUID->getByID($event['associated_event_id']);
            $event['associated_event_id'] = $eventIDPair['UUID'];
        }
        if (isset($event['location_id'])) {
            $locationIDPair = $registry->UUID->getByID($event['location_id']);
            $event['location_id'] = $locationIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($event);
});

$app->get('/v1/events/:eventUUID/invitees/', function($eventUUID) use ($app, $registry) {
    // Get all the invitees for a given event
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

        $invitees = $evt::getAllInvitees($eventID);
        foreach ($invitees as &$invitee) {
            $inviteeIDPair = $registry->UUID->getByID($invitee['invitee_id']);
            $invitee['invitee_id'] = $inviteeIDPair['UUID'];
            $eventIDPair = $registry->UUID->getByID($invitee['event_id']);
            $invitee['event_id'] = $eventIDPair['UUID'];
            if (isset($invitee['contact_id'])) {
                $contactIDPair = $registry->UUID->getByID($invitee['contact_id']);
                $invitee['contact_id'] = $contactIDPair['UUID'];
            }
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($invitees);
});

$app->get('/v1/events/:eventUUID/invitees/:inviteeUUID', function($eventUUID, $inviteeUUID) use ($app, $registry) {
    // Retrieve existing invitee
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

        $dbInvitee = $evt::getInvitee($inviteeID);
        if ($dbInvitee == false || $dbInvitee['event_id'] != $eventID) {
            $app->halt(404);
        }
        
        $inviteeIDPair = $registry->UUID->getByID($dbInvitee['invitee_id']);
        $dbInvitee['invitee_id'] = $inviteeIDPair['UUID'];
        $eventIDPair = $registry->UUID->getByID($dbInvitee['event_id']);
        $dbInvitee['event_id'] = $eventIDPair['UUID'];
        if (isset($dbInvitee['contact_id'])) {
            $contactIDPair = $registry->UUID->getByID($dbInvitee['contact_id']);
            $dbInvitee['contact_id'] = $contactIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbInvitee);
});

$app->get('/v1/locations/', function() use ($app, $registry) {
    // Get all locations current user created
    $loc = new \RANDF\PEI\Data\Locations();

    try {
        $locations = $loc::getAll($registry->UserID);
        foreach ($locations as &$location) {
            $locationIDPair = $registry->UUID->getByID($location['location_id']);
            $location['location_id'] = $locationIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($locations);
});

$app->get('/v1/locations/:locationUUID', function($locationUUID) use ($app, $registry) {
    // Retrieves existing location
    $loc = new \RANDF\PEI\Data\Locations();

    try {
        $locationIDPair = $registry->UUID->getByUUID($locationUUID);
        if ($locationIDPair === null) {
            $app->halt(404);
        }
        $locationID = $locationIDPair['ID'];
        
        $dbLocation = $loc::get($locationID);
        $dbLocation['location_id'] = $locationIDPair['UUID'];
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($dbLocation);
});

$app->get('/v1/contacts/', function() use ($app, $registry) {
    // Gets all of the current user's contacts
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contacts = $con::getAll($registry->UserID);
        foreach ($contacts as &$contact) {
            $contactIDPair = $registry->UUID->getByID($contact['contact_id']);
            $contact['contact_id'] = $contactIDPair['UUID'];
            if (isset($contact['location_id'])) {
                $locationIDPair = $registry->UUID->getByID($contact['location_id']);
                $contact['location_id'] = $locationIDPair['UUID'];
            }
            if (isset($contact['current_status_id'])) {
                $statusIDPair = $registry->UUID->getByID($contact['current_status_id']);
                $contact['current_status_id'] = $statusIDPair['UUID'];
            }
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($contacts);
});

$app->get('/v1/contacts/:contactUUID', function($contactUUID) use ($app, $registry) {
    // Retrieves existing contact
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contactIDPair = $registry->UUID->getByUUID($contactUUID);
        if ($contactIDPair === null) {
            $app->halt(404);
        }
        $contactID = $contactIDPair['ID'];
        
        $contact = $con::get($registry->UserID, $contactID);
        $contactIDPair = $registry->UUID->getByID($contact['contact_id']);
        $contact['contact_id'] = $contactIDPair['UUID'];
        if (isset($contact['location_id'])) {
            $locationIDPair = $registry->UUID->getByID($contact['location_id']);
            $contact['location_id'] = $locationIDPair['UUID'];
        }
        if (isset($contact['current_status_id'])) {
            $statusIDPair = $registry->UUID->getByID($contact['current_status_id']);
            $contact['current_status_id'] = $statusIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($contact);
});

$app->get('/v1/contacts/:contactUUID/statuses/', function($contactUUID) use ($app, $registry) {
    // Gets all statuses for the specified contact
    $con = new \RANDF\PEI\Data\Contacts();

    try {
        $contactIDPair = $registry->UUID->getByUUID($contactUUID);
        if ($contactIDPair === null) {
            $app->halt(404);
        }
        $contactID = $contactIDPair['ID'];
        
        $statuses = $con::getAllStatuses($contactID);
        foreach ($statuses as &$status) {
            $statusIDPair = $registry->UUID->getByID($status['status_id']);
            $status['status_id'] = $statusIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($statuses);
});

$app->get('/v1/contacts/:contactUUID/statuses/:statusUUID', function($contactUUID, $statusUUID) use ($app, $registry) {
    // Gets one status for a particular contact
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
        
        $status = $con::getStatus($statusID);
        if ($status === false) {
            $app->halt(404);
        }
        
        $statusIDPair = $registry->UUID->getByID($status['status_id']);
        $status['status_id'] = $statusIDPair['UUID'];
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($status);
});

$app->get('/v1/reminders/', function() use ($app, $registry) {
    // Gets all of the current user's reminders
    $rem = new \RANDF\PEI\Data\Reminders();

    try {
        $reminders = $rem::getAll($registry->UserID);
        foreach ($reminders as &$reminder) {
            $reminderIDPair = $registry->UUID->getByID($reminder['reminder_id']);
            $reminder['reminder_id'] = $reminderIDPair['UUID'];
            if (isset($reminder['event_id'])) {
                $eventIDPair = $registry->UUID->getByID($reminder['event_id']);
                $reminder['event_id'] = $eventIDPair['UUID'];
            }
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($reminders);
});

$app->get('/v1/reminders/:reminderUUID', function($reminderUUID) use ($app, $registry) {
    // Retrieves existing reminder
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

        $reminder = $rem::get($reminderID);
        
        $reminder['reminder_id'] = $reminderIDPair['UUID'];
        if (isset($reminder['event_id'])) {
            $eventIDPair = $registry->UUID->getByID($reminder['event_id']);
            $reminder['event_id'] = $eventIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($reminder);
});

$app->get('/v1/notifications/generate_test/', function() use ($app, $registry) {
    // Retrieves existing notifications
    $not = new \RANDF\PEI\Data\Notifications();
    
    $not->_createTestNotifications($registry->UserID);
});

$app->get('/v1/notifications/', function() use ($app, $registry) {
    // Retrieves existing notifications
    $not = new \RANDF\PEI\Data\Notifications();

    try {
        if ($app->request()->get('only_unread')) {
            $onlyUnread = true;
        } else {
            $onlyUnread = false;
        }
        $notifications = $not::getAll($registry->UserID, $onlyUnread);
        foreach ($notifications as &$notification) {
            $notificationIDPair = $registry->UUID->getByID($notification['notification_id']);
            $notification['notification_id'] = $notificationIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($notifications);
});

$app->get('/v1/notifications/:notificationUUID', function($notificationUUID) use ($app, $registry) {
    // Retrieves existing notification
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

$app->get('/v1/interactions/', function() use ($app, $registry) {
    // Gets all of the current user's interactions
    $int = new \RANDF\PEI\Data\Interactions();

    try {
        $interactions = $int::getAll($registry->UserID);
        
        foreach ($interactions as &$interaction) {
            $interactionIDPair = $registry->UUID->getByID($interaction['interaction_id']);
            $interaction['interaction_id'] = $interactionIDPair['UUID'];
            if (isset($interaction['contact_id'])) {
                $contactIDPair = $registry->UUID->getByID($interaction['contact_id']);
                $interaction['contact_id'] = $contactIDPair['UUID'];
            }
            if (isset($interaction['location_id'])) {
                $locationIDPair = $registry->UUID->getByID($interaction['location_id']);
                $interaction['location_id'] = $locationIDPair['UUID'];
            }
            if (isset($interaction['template_id'])) {
                $templateIDPair = $registry->UUID->getByID($interaction['template_id']);
                $interaction['template_id'] = $templateIDPair['UUID'];
            }
            if (isset($interaction['event_id'])) {
                $eventIDPair = $registry->UUID->getByID($interaction['event_id']);
                $interaction['event_id'] = $eventIDPair['UUID'];
            }
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($interactions);
});

$app->get('/v1/interactions/:interactionUUID', function($interactionUUID) use ($app, $registry) {
    // Retrieves existing interaction
    $int = new \RANDF\PEI\Data\Interactions();

    try {
        $interactionIDPair = $registry->UUID->getByUUID($interactionUUID);
        if ($interactionIDPair === null) {
            $app->halt(404);
        }
        $interactionID = $interactionIDPair['ID'];
        
        $interaction = $int::get($interactionID);
        $interaction['interaction_id'] = $interactionUUID;
        if (isset($interaction['contact_id'])) {
            $contactIDPair = $registry->UUID->getByID($interaction['contact_id']);
            $interaction['contact_id'] = $contactIDPair['UUID'];
        }
        if (isset($interaction['location_id'])) {
            $locationIDPair = $registry->UUID->getByID($interaction['location_id']);
            $interaction['location_id'] = $locationIDPair['UUID'];
        }
        if (isset($interaction['template_id'])) {
            $templateIDPair = $registry->UUID->getByID($interaction['template_id']);
            $interaction['template_id'] = $templateIDPair['UUID'];
        }
        if (isset($interaction['event_id'])) {
            $eventIDPair = $registry->UUID->getByID($interaction['event_id']);
            $interaction['event_id'] = $eventIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($interaction);
});

$app->get('/v1/templates/', function() use ($app, $registry) {
    // Gets all of the current user's templates
    $tpl = new \RANDF\PEI\Data\Templates();

    try {
        $templates = $tpl::getAll($registry->UserID);
        foreach ($templates as &$template) {
            $templateIDPair = $registry->UUID->getByID($template['template_id']);
            $template['template_id'] = $templateIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($templates);
});

$app->get('/v1/templates/:templateUUID', function($templateUUID) use ($app, $registry) {
    // Retrieves existing template
    $tpl = new \RANDF\PEI\Data\Templates();

    try {
        $templateIDPair = $registry->UUID->getByUUID($templateUUID);
        if ($templateIDPair === null) {
            $app->halt(404);
        }
        $templateID = $templateIDPair['ID'];

        $template = $tpl::get($templateID);
        $template['template_id'] = $templateUUID;
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($template);
});

$app->get('/v1/recordings/', function() use ($app, $registry) {
    // Gets all of the current user's recordings
    $rec = new \RANDF\PEI\Data\Recordings();

    try {
        $recordings = $rec::getAll($registry->UserID);
        foreach ($recordings as $i => &$recording) {
            if (!isset($recording['has_recording_data']) || $recording['has_recording_data'] != 1) {
                unset($recordings[$i]);
            }
            
            $recordingIDPair = $registry->UUID->getByID($recording['recording_id']);
            $recording['recording_id'] = $recordingIDPair['UUID'];
            if (isset($recording['contact_id'])) {
                try {
                    $contactIDPair = $registry->UUID->getByID($recording['contact_id']);
                    $recording['contact_id'] = $contactIDPair['UUID'];
                } catch (\RuntimeException $e) {
                    unset($recordings[$i]);
                    continue;
                }
            }
        }
        $recordings = array_values($recordings);
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($recordings);
});

$app->get('/v1/recordings/:recordingUUID', function($recordingUUID) use ($app, $registry) {
    // Retrieves existing recording
    $rec = new \RANDF\PEI\Data\Recordings();

    try {
        $recordingIDPair = $registry->UUID->getByUUID($recordingUUID);
        if ($recordingUUID === null) {
            $app->halt(404);
        }
        $recordingID = $recordingIDPair['ID'];

        $recording = $rec::get($recordingID);
        $recording['recording_id'] = $recordingUUID;
        
        if (!isset($recording['has_recording_data']) || $recording['has_recording_data'] != 1) {
            $app->halt(404);
        }
        if (isset($recording['contact_id'])) {
            $contactIDPair = $registry->UUID->getByID($recording['contact_id']);
            $recording['contact_id'] = $contactIDPair['UUID'];
        }
    } catch (\InvalidArgumentException $e) {
        $app->halt(400, $e->getMainMessage());
    } catch (\RuntimeException $e) {
        $app->halt(500, $e->getMessage());
    }

    echo Core::json_encode($recording);
});
