# ChronoLMS theme (theme_chronolms)

A Moodle theme for Chronosphere Academy, built for school students in grades 6 to 12. It is a child of Boost.

## Features

- Student dashboard with progress, streaks, a class leaderboard, levels and milestone badges.
- Points come from real Moodle data: 20 per completed activity and 5 per active day.
- One-time celebrations when a student gains points, levels up or earns a badge. They respect reduced-motion settings.
- Learning Path, Progress and Tests & Assessments pages built from course completion and gradebook data.
- Styled pages for the VPL and NextBlocks coding activities.
- A collapsible navy sidebar, a custom top bar and line icons.

## Requirements

- Moodle 4.1 or later (developed and tested on Moodle 5.1).
- theme_boost (part of Moodle core).
- Optional: mod_vpl and mod_nextblocks for the coding lab pages.
- Points and progress need completion tracking turned on for the course and its activities.

## Installation

1. Go to Site administration > Plugins > Install plugins and upload the ZIP.
   Or unzip it into `theme/chronolms` (in Moodle 5.x, `public/theme/chronolms`).
2. Visit Site administration > Notifications to finish the install.
3. Select ChronoLMS in Site administration > Appearance > Themes.
4. Purge all caches.

No other setup steps are needed. The compiled CSS (`style/chronolms.css`) and the AMD builds are included.

## Privacy

The theme stores one user preference, `theme_chronolms_seen`, so each celebration is shown only once. It is declared through the Privacy API.

## Licence

GNU GPL v3 or later. See LICENSE.txt.

Copyright 2026 Chronosphere Academy.


## Support

- Report bugs and request features: https://github.com/MVA-Chronosphere/ChronoLMSTheme/issues
- Source code: https://github.com/MVA-Chronosphere/ChronoLMSTheme
