---
title: 'Concepts'
description: 'How the delivery layer works, and the reasoning behind its shape.'
---

# Concepts

The package is small, but the way its parts hand off to each other is not obvious from any single file. These pages trace that.

- [How a notification is delivered](1.how-a-notification-is-delivered.md) — the path from `Notification::send()` to a stored row, a broadcast and a mail.
- [The gate chain](2.the-gate-chain.md) — the four gates, in order, and why the last one belongs to the application.
- [Types and channels](3.types-and-channels.md) — why a type is an enum case and a channel is an interface.
- [How a preference resolves](4.how-a-preference-resolves.md) — three tiers, sparse storage, and why no row means undecided.
