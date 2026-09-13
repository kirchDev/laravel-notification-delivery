---
title: 'laravel-notification-delivery documentation'
description: 'How the delivery layer is put together, and how a consuming application builds on it.'
icon: 'lucide:book-open-text'
navigation: false
---

This package adds three things to Laravel's notifications: persistence with read state, per-recipient channel preferences, and a gate chain that decides delivery before `via()` runs. These pages cover the parts a reader cannot recover from the source — how the pieces relate, and why they are shaped the way they are.

## Sections

::page-cards
::

Everything else has a home that cannot go stale: installation and the API surface are in the [README](https://github.com/kirchDev/laravel-notification-delivery/blob/main/README.md), every configuration key is documented inline in [`config/notification-delivery.php`](https://github.com/kirchDev/laravel-notification-delivery/blob/main/config/notification-delivery.php), the development setup is in [CONTRIBUTING.md](https://github.com/kirchDev/laravel-notification-delivery/blob/main/CONTRIBUTING.md), and consuming applications that run Laravel Boost get the same material through `resources/boost/`.
