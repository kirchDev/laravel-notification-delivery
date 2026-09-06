---
title: 'laravel-notification-delivery documentation'
description: 'How the delivery layer is put together, and how a consuming application builds on it.'
---

# laravel-notification-delivery

This package adds three things to Laravel's notifications: persistence with read state, per-recipient channel preferences, and a gate chain that decides delivery before `via()` runs. These pages cover the parts a reader cannot recover from the source — how the pieces relate, and why they are shaped the way they are.

## Sections

- [Concepts](1.concepts/) — the delivery path end to end, the gate chain, and the two seams an application extends through.
- [Guides](2.guides/) — the tasks a consuming application performs: sending, adding a channel, building the settings page, deciding when to stay quiet.

Everything else has a home that cannot go stale: installation and the API surface are in the [README](../README.md), every configuration key is documented inline in [`config/notification-delivery.php`](../config/notification-delivery.php), the development setup is in [CONTRIBUTING.md](../CONTRIBUTING.md), and consuming applications that run Laravel Boost get the same material through `resources/boost/`.
