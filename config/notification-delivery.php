<?php

declare(strict_types=1);

use KirchDev\NotificationDelivery\Enums\CoreChannel;
use KirchDev\NotificationDelivery\Models\DeliveredNotification;
use KirchDev\NotificationDelivery\Models\NotificationPreference;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | The Eloquent models backing the two tables. Applications may swap these
    | for their own subclasses (for example to add casts or scopes).
    |
    | Resolve them through this config rather than naming the packaged class:
    | an application without a morph map stores the class it resolved, so a row
    | written through the packaged class names a different class than every row
    | beside it and quietly stops matching.
    |
    */

    'models' => [
        'notification' => DeliveredNotification::class,
        'preference' => NotificationPreference::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Change these if your application already owns tables with the default
    | names, or if you publish and customise the migrations.
    |
    | "notifications" is deliberately not called `notifications`: Laravel's own
    | database channel claims that name, and leaving it free is what lets an
    | application keep using that channel alongside this package.
    |
    */

    'table_names' => [
        'notifications' => 'delivered_notifications',
        'preferences' => 'notification_preferences',
    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    |
    | The recipient is polymorphic, so this is the morph key column. Change it
    | to "notifiable_uuid" and the like if that is your convention. The morph
    | *type* column is fixed at `notifiable_type` — a class name is a string on
    | every key type, so there is nothing to configure.
    |
    */

    'column_names' => [
        'notifiable_morph_key' => 'notifiable_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Key Types
    |--------------------------------------------------------------------------
    |
    | Column types used by the package migrations and models. Configure them
    | BEFORE running the migrations: the migrations read this config at run time
    | and bake the types into the schema, so a later change needs an
    | application-owned migration.
    |
    | "notifiable_morph_key_type" must match the key type of the models you
    | notify, or the morph key will not line up with the ids you store in it.
    |
    | Supported: "id", "uuid", "ulid"
    |
    */

    'keys' => [
        'primary_key_type' => 'id',
        'notifiable_morph_key_type' => 'id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    |
    | The channel enums this application exposes. A settings page has to render
    | a column for a channel nothing has been sent on yet, and that list cannot
    | be derived from stored rows — only declared.
    |
    | Add your own enum implementing KirchDev\NotificationDelivery\Contracts\
    | Channel to wire in push, SMS or anything else. Where two enums declare the
    | same backed value, the first listed wins.
    |
    */

    'channels' => [
        'enums' => [
            CoreChannel::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Type Discovery
    |--------------------------------------------------------------------------
    |
    | The notification type enums this application declares. Registration is
    | explicit rather than scanned: a filesystem scan is both slow on every boot
    | and wrong the moment a type ships inside a package.
    |
    | Sending a notification needs nothing from this list — it is what lets a
    | settings page enumerate every type a recipient can configure.
    |
    */

    'discovery' => [
        'types' => [
            // App\Enums\Notification\OrganisationNotification::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Suppression (gate 4)
    |--------------------------------------------------------------------------
    |
    | The one gate the package does not own: is this a bad moment for a channel
    | that interrupts out of band?
    |
    | "policy" of null means auto — NeverSuppress, upgraded to the presence-aware
    | DeviceSessionSuppression when kirchdev/laravel-device-sessions is
    | installed. Name a class here to bind your own and switch that off.
    |
    | The two windows below are read by DeviceSessionSuppression, in seconds:
    | "presence_window" is how recently a device must have been seen for the
    | recipient to count as present, and "defer" is how long a quietable channel
    | is then held back before the job re-checks whether they read it.
    |
    */

    'suppression' => [
        'policy' => null,
        'presence_window' => 300,
        'defer' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | notification-delivery:prune deletes stored notifications past their
    | retention window. Two windows, because read notifications go stale faster
    | than unread ones.
    |
    | Both default to null: NEVER DELETE. A record of who was invited when can be
    | the reason somebody still has access years later, and that call belongs to
    | the application, not to a package default. The command ships unscheduled.
    |
    */

    'prune' => [
        'retention_days' => null,
        'retention_days_read' => null,
    ],

];
