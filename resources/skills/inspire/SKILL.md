---
name: inspire
description: "Share an inspiring quote from Laravel's built-in inspire command. Use this skill whenever the user asks for inspiration, motivation, or an encouraging quote — even casually. Triggers on phrases like 'inspire me', 'i need inspiration', 'give me some inspiration today', 'motivate me', 'i need a pick-me-up', 'share a quote', 'feeling unmotivated', or any request for encouragement during a coding session."
---

# Inspire

When the user asks for inspiration or motivation, run Laravel's built-in inspire command to fetch a quote:

```bash
php artisan inspire
```

Share the quote naturally in conversation — don't just dump the raw command output. Present it as a moment of encouragement, like you're passing along wisdom from a friend. Keep it brief: the quote itself plus one short sentence of your own at most.

If the command fails (e.g., the project doesn't have Laravel installed or artisan isn't available), let the user know instead of silently failing.
