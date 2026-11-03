<?php
// This file is part of the ChronoLMS theme.

/**
 * Learning paths: the tracks a student can follow, which Moodle course each stage is, and how far the
 * student has got. Shared by the Learning Path page and the dashboard badges so they always agree.
 *
 * What is data and what is copy:
 *   - Progress, status and badges come from the student's real course completion.
 *   - Titles, descriptions, skills and badge names are curated text (tracks() below).
 *   - Each stage is tied to a course by its exact course name (case-insensitive), preferring one the
 *     student is enrolled in, with the production course id as a fallback.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_chronolms;

defined('MOODLE_INTERNAL') || die();

class learning_path {

    /** @return array track id => track definition (curated content) */
    public static function tracks(): array {
    return [
        'block' => [
            'id'           => 'block',
            'title'        => 'Block Coding & Visual Logic',
            'target_grade' => 'Grades 6–8 Foundation',
            'grade_tag'    => 'Grades 6–8',
            'icon'         => \theme_chronolms\icons::svg('puzzle'),
            'badge'        => 'Visual Logic Track',
            'desc'         => 'Building strong computational thinking, algorithmic sequences, event-driven loops, and problem solving visually using NextBlocks Studio before transitioning to text syntax in Grade 9.',
            'stages'       => [
                1 => [
                    'courses'     => ['Introduction to Programming using Block Coding'],
                    'fallback_id' => 3,
                    'tag'         => 'STAGE 01 • COMPUTATIONAL THINKING',
                    'title'       => 'Introduction to Block-Based Coding',
                    'subtitle'    => 'Master sequencing, canvas coordinates, sprite controls, and event-driven commands in NextBlocks Studio.',
                    'skills'      => ['Visual Sequences', 'Command Blocks', 'Sprites & Canvas', 'Basic Loops'],
                    'tool_badge'  => 'NextBlocks Studio',
                    'tool_icon'   => \theme_chronolms\icons::svg('puzzle'),
                    'badge_name'  => 'Block Builder',
                    'badge_icon'  => \theme_chronolms\icons::svg('puzzle'),
                ],
                2 => [
                    'courses'     => ['Programming Using Block Based Coding', 'Block Based Coding'],
                    'fallback_id' => 29,
                    'tag'         => 'STAGE 02 • LOOPS & EVENTS',
                    'title'       => 'Interactive Games & Event-Driven Logic',
                    'subtitle'    => 'Build interactive arcade games, keyboard controls, collision detection, and conditional decision trees.',
                    'skills'      => ['Event Handlers', 'Nested Loops', 'Conditionals', 'Variables in Blocks'],
                    'tool_badge'  => 'Interactive Block Studio',
                    'tool_icon'   => \theme_chronolms\icons::svg('gamepad'),
                    'badge_name'  => 'Logic Explorer',
                    'badge_icon'  => \theme_chronolms\icons::svg('zap'),
                ],
                3 => [
                    'courses'     => ['Bridge to Coding'],
                    'fallback_id' => 12,
                    'tag'         => 'STAGE 03 • SYNTAX PREPARATION',
                    'title'       => 'Bridge to Text Coding & Algorithms',
                    'subtitle'    => 'Bridge visual block logic into structured algorithmic thinking to prepare for text-based Python in Grade 9.',
                    'skills'      => ['Algorithm Planning', 'Debugging Techniques', 'Block-to-Text Mapping', 'Project Showcase'],
                    'tool_badge'  => 'Bridge Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('rocket'),
                    'badge_name'  => 'Junior Coder',
                    'badge_icon'  => \theme_chronolms\icons::svg('trophy'),
                ],
            ],
        ],

        'ai' => [
            'id'           => 'ai',
            'title'        => 'Artificial Intelligence & Data Science',
            'target_grade' => 'Grade 9+ Specialization',
            'grade_tag'    => 'Grade 9+',
            'icon'         => \theme_chronolms\icons::svg('bot'),
            'badge'        => 'AI Specialization',
            'desc'         => 'From foundational Python programming into exploratory data analysis, dataset manipulation, statistical computing, and machine learning models.',
            'stages'       => [
                1 => [
                    'courses'     => ['Python Refresher Course', 'Python for Beginners', 'Fundamentals of Programming Python'],
                    'fallback_id' => 4,
                    'tag'         => 'STAGE 01 • COMMON CORE',
                    'title'       => 'Python Foundations & Data Syntax',
                    'subtitle'    => 'Universal foundation for all Grade 9+ students: control flow, lists, dictionaries, modular functions, and autograded VPL challenges.',
                    'skills'      => ['Python Syntax', 'Data Structures (Lists/Dicts)', 'Functions & Modules', 'Algorithmic Problem Solving'],
                    'tool_badge'  => 'Python Interactive Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('terminal'),
                    'badge_name'  => 'Python Pioneer',
                    'badge_icon'  => \theme_chronolms\icons::svg('terminal'),
                ],
                2 => [
                    'courses'     => ['Level 1 - Data Science Specialization'],
                    'fallback_id' => 11,
                    'tag'         => 'STAGE 02 • DATA SCIENCE SPECIALIZATION',
                    'title'       => 'Data Science & Analytical Computing',
                    'subtitle'    => 'Clean, transform, and analyze real-world datasets with statistical Python techniques and data visualization plots.',
                    'skills'      => ['Data Wrangling', 'Statistical Methods', 'Plotting & Charts', 'Dataset Modeling'],
                    'tool_badge'  => 'Data Science Sandbox',
                    'tool_icon'   => \theme_chronolms\icons::svg('bar-chart'),
                    'badge_name'  => 'Data Analyst',
                    'badge_icon'  => \theme_chronolms\icons::svg('bar-chart'),
                ],
                3 => [
                    'courses'     => ['Data Science Test'],
                    'fallback_id' => 36,
                    'tag'         => 'STAGE 03 • APPLIED AI & EVALUATION',
                    'title'       => 'Machine Learning Models & AI Capstone',
                    'subtitle'    => 'Train predictive classifiers, evaluate precision and recall, and build an end-to-end AI project portfolio.',
                    'skills'      => ['Predictive Modeling', 'Feature Engineering', 'Model Evaluation', 'AI Capstone'],
                    'tool_badge'  => 'AI Lab & Evaluation',
                    'tool_icon'   => \theme_chronolms\icons::svg('lightbulb'),
                    'badge_name'  => 'AI Specialist',
                    'badge_icon'  => \theme_chronolms\icons::svg('bot'),
                ],
            ],
        ],

        'robotics' => [
            'id'           => 'robotics',
            'title'        => 'Robotics & Embedded IoT',
            'target_grade' => 'Grade 9+ Specialization',
            'grade_tag'    => 'Grade 9+',
            'icon'         => \theme_chronolms\icons::svg('cpu'),
            'badge'        => 'Robotics Specialization',
            'desc'         => 'Combine programmatic logic with real physical hardware: sensor telemetry, motor controllers, autonomous navigation, and IoT connectivity.',
            'stages'       => [
                1 => [
                    'courses'     => ['Python Refresher Course', 'Python for Beginners', 'Fundamentals of Programming Python'],
                    'fallback_id' => 4,
                    'tag'         => 'STAGE 01 • COMMON CORE',
                    'title'       => 'Python Programming & Embedded Logic',
                    'subtitle'    => 'Master programmatic logic, timed execution loops, conditional branching, and hardware control logic.',
                    'skills'      => ['Control Flow', 'Time Delays & Clocks', 'Logical Operators', 'Hardware Logic'],
                    'tool_badge'  => 'Python Interactive Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('terminal'),
                    'badge_name'  => 'Embedded Coder',
                    'badge_icon'  => \theme_chronolms\icons::svg('terminal'),
                ],
                2 => [
                    'courses'     => ['Robotics Foundational Course'],
                    'fallback_id' => 5,
                    'tag'         => 'STAGE 02 • SENSORS & ACTUATORS',
                    'title'       => 'Robotics Foundational Course',
                    'subtitle'    => 'Explore circuits, digital/analog sensors, servo motors, and microcontroller interfaces.',
                    'skills'      => ['Sensors & Actuators', 'GPIO Pinouts', 'PWM Motor Drivers', 'Circuit Schematics'],
                    'tool_badge'  => 'Robotics Virtual Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('bot'),
                    'badge_name'  => 'Robo Pioneer',
                    'badge_icon'  => \theme_chronolms\icons::svg('cpu'),
                ],
                3 => [
                    'courses'     => ['Robotics Advanced Course'],
                    'fallback_id' => 6,
                    'tag'         => 'STAGE 03 • AUTONOMOUS SYSTEMS',
                    'title'       => 'Robotics Advanced Course',
                    'subtitle'    => 'Build autonomous navigation systems, line-following PID control algorithms, and obstacle avoidance robots.',
                    'skills'      => ['PID Control Loops', 'Autonomous Navigation', 'Ultrasonic Mapping', 'Telemetry Data'],
                    'tool_badge'  => 'Autonomous Simulator',
                    'tool_icon'   => \theme_chronolms\icons::svg('settings'),
                    'badge_name'  => 'Robotics Engineer',
                    'badge_icon'  => \theme_chronolms\icons::svg('zap'),
                ],
                4 => [
                    'courses'     => ['Robotics Expert Course'],
                    'fallback_id' => 10,
                    'tag'         => 'STAGE 04 • CLOUD IOT & AUTOMATION',
                    'title'       => 'Robotics Expert Course & IoT',
                    'subtitle'    => 'Connect embedded robots to wireless networks, cloud dashboards, and automated remote systems.',
                    'skills'      => ['IoT Protocols', 'Cloud Telemetry', 'Wireless Controllers', 'Full Hardware Capstone'],
                    'tool_badge'  => 'IoT Hardware Sandbox',
                    'tool_icon'   => \theme_chronolms\icons::svg('globe'),
                    'badge_name'  => 'Robotics Master',
                    'badge_icon'  => \theme_chronolms\icons::svg('trophy'),
                ],
            ],
        ],

        'web' => [
            'id'           => 'web',
            'title'        => 'Web & Full Stack Development',
            'target_grade' => 'Grade 9+ Specialization',
            'grade_tag'    => 'Grade 9+',
            'icon'         => \theme_chronolms\icons::svg('globe'),
            'badge'        => 'Full Stack Specialization',
            'desc'         => 'Architect modern web experiences: semantic HTML structure, responsive CSS design, dynamic JavaScript, and full-stack app deployment.',
            'stages'       => [
                1 => [
                    'courses'     => ['HyperText Markup Language'],
                    'fallback_id' => 7,
                    'tag'         => 'STAGE 01 • WEB STRUCTURE',
                    'title'       => 'HyperText Markup Language (HTML5)',
                    'subtitle'    => 'Build accessible, semantic web layouts: document trees, forms, multimedia embeds, and modern web structures.',
                    'skills'      => ['HTML5 Elements', 'Web Forms', 'DOM Structure', 'Accessibility'],
                    'tool_badge'  => 'Web IDE',
                    'tool_icon'   => \theme_chronolms\icons::svg('file-text'),
                    'badge_name'  => 'HTML Architect',
                    'badge_icon'  => \theme_chronolms\icons::svg('globe'),
                ],
                2 => [
                    'courses'     => ['Cascading Style Sheets'],
                    'fallback_id' => 8,
                    'tag'         => 'STAGE 02 • MODERN STYLING',
                    'title'       => 'Cascading Style Sheets (CSS3)',
                    'subtitle'    => 'Style modern responsive web apps with CSS Flexbox, Grid, transitions, animations, and fluid typography.',
                    'skills'      => ['CSS Flexbox', 'CSS Grid', 'Responsive Layouts', 'Keyframe Animations'],
                    'tool_badge'  => 'CSS Style Studio',
                    'tool_icon'   => \theme_chronolms\icons::svg('palette'),
                    'badge_name'  => 'Style Craftsman',
                    'badge_icon'  => \theme_chronolms\icons::svg('palette'),
                ],
                3 => [
                    'courses'     => ['JavaScript', 'Javascript Programming'],
                    'fallback_id' => 9,
                    'tag'         => 'STAGE 03 • DYNAMIC SCRIPTING',
                    'title'       => 'JavaScript Programming',
                    'subtitle'    => 'Create dynamic, interactive user interfaces with modern ES6+ JavaScript, event listeners, and asynchronous API calls.',
                    'skills'      => ['ES6+ Syntax', 'DOM Manipulation', 'Event Listeners', 'Async / Fetch APIs'],
                    'tool_badge'  => 'JavaScript Console Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('zap'),
                    'badge_name'  => 'JavaScript Ninja',
                    'badge_icon'  => \theme_chronolms\icons::svg('zap'),
                ],
                4 => [
                    'courses'     => ['APP DEVELOPMENT'],
                    'fallback_id' => 13,
                    'tag'         => 'STAGE 04 • FULL STACK & APPS',
                    'title'       => 'App Development & Deployment',
                    'subtitle'    => 'Integrate front-end user interfaces with Python backend services, persistent storage, and production hosting.',
                    'skills'      => ['Full Stack Architecture', 'REST APIs', 'App State', 'Production Deployment'],
                    'tool_badge'  => 'Full Stack Studio',
                    'tool_icon'   => \theme_chronolms\icons::svg('rocket'),
                    'badge_name'  => 'Full Stack Engineer',
                    'badge_icon'  => \theme_chronolms\icons::svg('trophy'),
                ],
            ],
        ],

        'cyber' => [
            'id'           => 'cyber',
            'title'        => 'Cybersecurity & Defense',
            'target_grade' => 'Grade 9+ Specialization',
            'grade_tag'    => 'Grade 9+',
            'icon'         => \theme_chronolms\icons::svg('shield'),
            'badge'        => 'Cybersecurity Specialization',
            'desc'         => 'Master network defense, identify digital vulnerabilities, analyze security threats, and safeguard computing infrastructure.',
            'stages'       => [
                1 => [
                    'courses'     => ['Python Refresher Course', 'Python for Beginners', 'Fundamentals of Programming Python'],
                    'fallback_id' => 4,
                    'tag'         => 'STAGE 01 • COMMON CORE',
                    'title'       => 'Python Foundations for Security',
                    'subtitle'    => 'Learn essential Python scripting, socket networking basics, cryptographic hashing, and automated system scripts.',
                    'skills'      => ['Security Scripting', 'Sockets & Network I/O', 'Hashing & Cryptography', 'Log Parsing'],
                    'tool_badge'  => 'Python Security Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('terminal'),
                    'badge_name'  => 'Security Initiate',
                    'badge_icon'  => \theme_chronolms\icons::svg('terminal'),
                ],
                2 => [
                    'courses'     => ['Cybersecurity Foundational Course'],
                    'fallback_id' => 24,
                    'tag'         => 'STAGE 02 • SECURITY PRINCIPLES',
                    'title'       => 'Cybersecurity Foundational Course',
                    'subtitle'    => 'Understand the CIA triad, authentication protocols, network layers, and standard threat classification.',
                    'skills'      => ['CIA Triad', 'Threat Vectors', 'Authentication Protocols', 'Encryption Basics'],
                    'tool_badge'  => 'Security Fundamentals Lab',
                    'tool_icon'   => \theme_chronolms\icons::svg('shield'),
                    'badge_name'  => 'Cyber Defender',
                    'badge_icon'  => \theme_chronolms\icons::svg('shield'),
                ],
                3 => [
                    'courses'     => ['Cybersecurity Intermediate Course'],
                    'fallback_id' => 25,
                    'tag'         => 'STAGE 03 • NETWORK DEFENSE',
                    'title'       => 'Cybersecurity Intermediate Course',
                    'subtitle'    => 'Hands-on packet analysis, firewall configuration, vulnerability scanning, and incident defense tactics.',
                    'skills'      => ['Packet Inspection', 'Firewall Rules', 'Vulnerability Audits', 'System Hardening'],
                    'tool_badge'  => 'Network Defense Simulator',
                    'tool_icon'   => \theme_chronolms\icons::svg('lock'),
                    'badge_name'  => 'Security Analyst',
                    'badge_icon'  => \theme_chronolms\icons::svg('zap'),
                ],
                4 => [
                    'courses'     => ['Cybersecurity Advanced Course'],
                    'fallback_id' => 26,
                    'tag'         => 'STAGE 04 • ADVANCED DEFENSE & EXAM',
                    'title'       => 'Cybersecurity Advanced Course & Exam',
                    'subtitle'    => 'Digital forensics investigations, penetration defense testing, and formal certification examination.',
                    'skills'      => ['Digital Forensics', 'Incident Response', 'Security Audits', 'Certification Exam'],
                    'tool_badge'  => 'Cybersecurity Range',
                    'tool_icon'   => \theme_chronolms\icons::svg('trophy'),
                    'badge_name'  => 'Cyber Sentinel',
                    'badge_icon'  => \theme_chronolms\icons::svg('trophy'),
                ],
            ],
        ],
    ];
    }

    /**
     * Picks the track to show: the requested one, else grade 6-8 students get Block Coding, else the track
     * their enrolled courses point to most, else AI & Data.
     *
     * @param \stdClass[] $courses the student's enrolled courses
     * @param string $requested track id from the URL, if any
     * @param \stdClass|null $user student to decide for (default: the logged-in user; profile fields loaded)
     * @return string track id
     */
    public static function detect_track(array $courses, string $requested = '', ?\stdClass $user = null): string {
        global $DB, $USER;
        $user = $user ?? $USER;
        $tracks = self::tracks();
        if ($requested !== '' && isset($tracks[$requested])) {
            return $requested;
        }

        $middle = false;
        try {
            // Grade cohorts such as Class_6th, Class_7th, Class_8th.
            foreach ($DB->get_records_sql(
                "SELECT c.name FROM {cohort} c JOIN {cohort_members} cm ON cm.cohortid = c.id WHERE cm.userid = ?",
                [$user->id]
            ) as $coh) {
                if (preg_match('/(class_?|grade_?|[^\d]|^)[678](th|thpr)?([^\d]|$)/i', strtolower($coh->name))) {
                    $middle = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            $middle = false;
        }
        if (!$middle && !empty($user->profile['Student_Class'])) {
            $n = (int)preg_replace('/[^0-9]/', '', $user->profile['Student_Class']);
            $middle = $n >= 6 && $n <= 8;
        }
        if (!$middle && !empty($user->lastname)) {
            $middle = (bool)preg_match('/[_\-\s][678](th)?(\b|[_\-\s])/i', $user->lastname);
        }
        if ($middle) {
            return 'block';
        }

        // Otherwise: which track do the courses they are already in belong to?
        $scores = ['ai' => 0, 'robotics' => 0, 'web' => 0, 'cyber' => 0, 'block' => 0];
        foreach ($tracks as $tid => $track) {
            foreach ($track['stages'] as $stage) {
                foreach ($stage['courses'] as $name) {
                    foreach ($courses as $c) {
                        if (\core_text::strtolower(trim($c->fullname)) === \core_text::strtolower($name)) {
                            // Shared first stages (Python) say nothing about the track.
                            $scores[$tid] += ($stage['courses'][0] === 'Python Refresher Course') ? 0 : 1;
                        }
                    }
                }
            }
        }
        arsort($scores);
        $best = key($scores);
        return $scores[$best] > 0 ? $best : 'ai';
    }

    /**
     * Stages of a track with the student's real progress in each.
     *
     * @param string $trackid track id
     * @param \stdClass[] $courses the student's enrolled courses
     * @param int $userid student
     * @return array stages (each: definition + num, courseid, courseurl, enrolled, pct, done, total, status),
     *               completed, current (stage number), pct (average)
     */
    public static function stages(string $trackid, array $courses, int $userid): array {
        global $CFG;
        require_once($CFG->dirroot . '/theme/chronolms/lib.php');
        $tracks = self::tracks();
        $defs = $tracks[$trackid]['stages'];

        $byname = [];
        foreach ($courses as $c) {
            $byname[\core_text::strtolower(trim($c->fullname))] = $c;
        }

        $stages = [];
        $completed = 0;
        $current = 0;
        $sumpct = 0;
        foreach ($defs as $num => $def) {
            $course = null;
            foreach ($def['courses'] as $name) {
                $key = \core_text::strtolower(trim($name));
                if (isset($byname[$key])) {
                    $course = $byname[$key];
                    break;
                }
            }
            $enrolled = $course !== null;
            if (!$course) {
                // Not enrolled: still link to the course if it exists and is open to students.
                try {
                    $fallback = get_course($def['fallback_id']);
                    $course = $fallback->visible ? $fallback : null;
                } catch (\Throwable $e) {
                    $course = null;
                }
            }

            $prog = ['pct' => 0, 'done' => 0, 'total' => 0];
            if ($enrolled) {
                $prog = theme_chronolms_get_course_progress($course, $userid);
            }
            $done = $enrolled && $prog['pct'] >= 100;
            if ($done) {
                $completed++;
                $status = 'completed';
            } else if (!$current) {
                $current = $num;
                $status = 'in_progress';
            } else {
                $status = 'upcoming';
            }
            $sumpct += $prog['pct'];

            $stages[] = $def + [
                'num' => $num,
                'courseid' => $course ? (int)$course->id : 0,
                'courseurl' => $course ? (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false) : '',
                'enrolled' => $enrolled,
                'pct' => (int)$prog['pct'],
                'done' => (int)$prog['done'],
                'total' => (int)$prog['total'],
                'status' => $status,
            ];
        }
        return [
            'stages' => $stages,
            'completed' => $completed,
            'current' => $current ?: (count($defs) ? array_key_last($defs) : 0),
            'pct' => count($defs) ? (int)round($sumpct / count($defs)) : 0,
        ];
    }

    /**
     * The badges a student can earn: Streak Flame, plus one per stage of their track. A stage badge is
     * earned when that stage's course is complete. Used by the dashboard and the Progress page.
     *
     * @param \stdClass[] $courses the student's enrolled courses
     * @param int $userid student
     * @param array $streak result of theme_chronolms_get_streak()
     * @param \stdClass|null $user the student, when not the logged-in user
     * @return array badges, unlocked (count), next (the closest one still locked)
     */
    public static function badges(array $courses, int $userid, array $streak, ?\stdClass $user = null): array {
        $trackid = self::detect_track($courses, '', $user);
        $path = self::stages($trackid, $courses, $userid);
        $badges = [[
            'id'       => 'streak_flame',
            'name'     => 'Streak Flame',
            'icon'     => icons::svg('flame'),
            'desc'     => 'Maintain a 3+ day active learning streak',
            'unlocked' => ($streak['count'] >= 3),
            'pct'      => (int)min(100, round(($streak['count'] / 3) * 100)),
        ]];
        foreach ($path['stages'] as $st) {
            $badges[] = [
                'id'       => 'stage' . $st['num'],
                'name'     => $st['badge_name'],
                'icon'     => $st['badge_icon'],
                'desc'     => 'Finish stage ' . $st['num'] . ': ' . $st['title'],
                'unlocked' => ($st['status'] === 'completed'),
                'pct'      => $st['pct'],
            ];
        }
        $unlocked = 0;
        $next = null;
        foreach ($badges as $b) {
            if ($b['unlocked']) {
                $unlocked++;
            } else if ($next === null || $b['pct'] > $next['pct']) {
                $next = $b;
            }
        }
        return ['badges' => $badges, 'unlocked' => $unlocked, 'next' => $next ?: end($badges)];
    }
}
