<?php
/**
 * ChronoLMS Tests & Assessments: every quiz from the student's courses in one place,
 * grouped by what needs doing next.
 *
 * @package    theme_chronolms
 * @copyright  2026 Chronosphere Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();
require_once($CFG->dirroot . '/theme/chronolms/lib.php');

global $CFG, $OUTPUT, $PAGE, $USER, $DB;

$PAGE->set_url(new moodle_url('/theme/chronolms/assessments.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('testsassessments', 'theme_chronolms') . ' | ChronoLMS');
$PAGE->set_heading(get_string('testsassessments', 'theme_chronolms'));
theme_chronolms_setup_page();   // loads app.js and Bootstrap (these pages render their own HTML, not a layout file)

$renderedsidebar = $OUTPUT->render_from_template('theme_chronolms/nav/sidebar', theme_chronolms_get_sidebar_data());
$topbardata      = theme_chronolms_get_topbar_data();

$now = time();
$fmt = function (int $ts): string {
    return userdate($ts, get_string('strftimedatetimeshort', 'langconfig'));
};
$relative = function (int $ts) use ($now): string {
    $days = (int) floor(($ts - $now) / DAYSECS);
    if ($ts < $now) {
        return 'closed';
    }
    if ($days <= 0) {
        return 'today';
    }
    return $days === 1 ? 'tomorrow' : "in $days days";
};

$courses = enrol_get_my_courses(['id', 'fullname', 'shortname'], 'fullname ASC');
$rows = [];
if ($courses) {
    [$insql, $params] = $DB->get_in_or_equal(array_keys($courses), SQL_PARAMS_NAMED, 'c');
    $quizzes = $DB->get_records_select('quiz', "course $insql", $params, 'timeclose ASC, name ASC');
    $attempts = [];
    if ($quizzes) {
        [$qsql, $qparams] = $DB->get_in_or_equal(array_keys($quizzes), SQL_PARAMS_NAMED, 'q');
        $qparams['uid'] = $USER->id;
        foreach ($DB->get_records_select('quiz_attempts', "quiz $qsql AND userid = :uid AND preview = 0", $qparams, 'attempt ASC') as $a) {
            $attempts[$a->quiz][] = $a;
        }
        $grades = $DB->get_records_select('quiz_grades', "quiz $qsql AND userid = :uid", $qparams, '', 'quiz, grade');
    }
    foreach ($quizzes as $quiz) {
        $course = $courses[$quiz->course];
        $modinfo = get_fast_modinfo($course);
        $cm = null;
        foreach ($modinfo->get_instances_of('quiz') as $inst) {
            if ((int) $inst->instance === (int) $quiz->id) {
                $cm = $inst;
                break;
            }
        }
        if (!$cm || !$cm->uservisible) {
            continue;
        }

        $mine = $attempts[$quiz->id] ?? [];
        $finished = array_filter($mine, function ($a) {
            return $a->state === 'finished';
        });
        $inprogress = array_filter($mine, function ($a) {
            return $a->state === 'inprogress';
        });
        $pct = null;
        if (isset($grades[$quiz->id]) && $quiz->grade > 0) {
            $pct = (int) round($grades[$quiz->id]->grade / $quiz->grade * 100);
        }
        $open = (int) $quiz->timeopen;
        $close = (int) $quiz->timeclose;
        $canretry = !$quiz->attempts || count($mine) < $quiz->attempts;

        if ($inprogress) {
            $status = 'inprogress';
            $label = 'In progress';
            $action = 'Continue';
        } else if ($finished) {
            $status = 'done';
            $label = 'Completed';
            $action = ($canretry && (!$close || $close > $now)) ? 'Try again' : 'Review';
        } else if ($open && $open > $now) {
            $status = 'soon';
            $label = 'Not open yet';
            $action = 'Details';
        } else if ($close && $close < $now) {
            $status = 'missed';
            $label = 'Missed';
            $action = 'Details';
        } else {
            $status = 'todo';
            $label = 'To do';
            $action = 'Start';
        }

        $when = '';
        if ($status === 'soon') {
            $when = 'Opens ' . $fmt($open);
        } else if ($status === 'todo' || $status === 'inprogress') {
            $when = $close ? 'Due ' . $fmt($close) . ' (' . $relative($close) . ')' : 'No deadline';
        } else if ($status === 'missed') {
            $when = 'Closed ' . $fmt($close);
        } else if ($finished) {
            $last = end($finished);
            $when = 'Finished ' . $fmt((int) $last->timefinish);
        }

        $urgent = in_array($status, ['todo', 'inprogress'], true) && $close && ($close - $now) < 3 * DAYSECS;
        $sortkey = ['inprogress' => 0, 'todo' => 1, 'soon' => 2, 'done' => 3, 'missed' => 4][$status];
        $rows[] = [
            'sortgroup'  => $sortkey,
            'sortdate'   => $status === 'done' ? -(int) (end($finished)->timefinish) : ($close ?: PHP_INT_MAX),
            'name'       => format_string($quiz->name),
            'course'     => format_string($course->fullname),
            'url'        => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
            'status'     => $status,
            'group'      => in_array($status, ['todo', 'inprogress'], true) ? 'todo' : ($status === 'done' ? 'done' : 'other'),
            'statuslabel' => $label,
            'action'     => $action,
            'when'       => $when,
            'urgent'     => $urgent,
            'minutes'    => $quiz->timelimit ? (int) round($quiz->timelimit / 60) : 0,
            'attempts'   => $quiz->attempts ? $quiz->attempts : 0,
            'attemptsused' => count($mine),
            'haspct'     => $pct !== null,
            'pct'        => $pct,
            'pass'       => $pct !== null && $pct >= 50,
            'is_done'    => $status === 'done',
            'is_primary' => in_array($status, ['todo', 'inprogress'], true),
        ];
    }
}
usort($rows, function ($a, $b) {
    return [$a['sortgroup'], $a['sortdate']] <=> [$b['sortgroup'], $b['sortdate']];
});

$count = function (array $statuses) use ($rows): int {
    return count(array_filter($rows, function ($r) use ($statuses) {
        return in_array($r['status'], $statuses, true);
    }));
};
$scored = array_values(array_filter(array_column($rows, 'pct'), function ($p) {
    return $p !== null;
}));
$average = $scored ? (int) round(array_sum($scored) / count($scored)) : null;
$bestrow = null;
foreach ($rows as $r) {
    if ($r['haspct'] && (!$bestrow || $r['pct'] > $bestrow['pct'])) {
        $bestrow = $r;
    }
}
$nextdue = null;
foreach ($rows as $r) {
    if ($r['is_primary']) {
        $nextdue = $r;
        break;
    }
}

$templatedata = [
    'rows'        => $rows,
    'hasrows'     => !empty($rows),
    'total'       => count($rows),
    'todocount'   => $count(['todo', 'inprogress']),
    'donecount'   => $count(['done']),
    'othercount'  => $count(['soon', 'missed']),
    'hasaverage'  => $average !== null,
    'average'     => $average,
    'best'        => $bestrow,
    'nextdue'     => $nextdue,
    'urgentcount' => count(array_filter($rows, function ($r) {
        return $r['urgent'];
    })),
    'mycoursesurl' => (new moodle_url('/my/courses.php'))->out(false),
    'iconarrow'   => \theme_chronolms\icons::svg('arrow-right'),
    'iconclock'   => \theme_chronolms\icons::svg('clock'),
    'iconcheck'   => \theme_chronolms\icons::svg('check'),
    'iconfile'    => \theme_chronolms\icons::svg('file-text'),
    'icontrophy'  => \theme_chronolms\icons::svg('trophy'),
    'iconflag'    => \theme_chronolms\icons::svg('flag'),
];

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo get_string('testsassessments', 'theme_chronolms'); ?> | ChronoLMS</title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body <?php echo $OUTPUT->body_attributes(['chronolms-authenticated', 'chronolms-assessments']); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<div class="chronolms-app" id="page">
    <?php echo $renderedsidebar; ?>

    <div class="chronolms-content-wrapper" id="chronolms-content-wrapper">
        <?php echo $OUTPUT->render_from_template('theme_chronolms/nav/topbar', $topbardata); ?>

        <main class="chronolms-main chronolms-assessments-page" id="page-content" role="main">
            <section id="region-main" aria-label="<?php echo get_string('content'); ?>">
            <?php echo $OUTPUT->render_from_template('theme_chronolms/assessments/page', $templatedata); ?>
            </section>
        </main>
    </div>
</div>

<button id="chronolms-chatbot-toggle" aria-label="Open AI assistant">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
</button>
<div id="chronolms-chatbot-panel" role="dialog">
    <div class="chatbot-header">
        <div class="bot-avatar"><?php echo \theme_chronolms\icons::svg('bot'); ?></div>
        <div class="bot-info"><div class="bot-name">ChronoBot</div><div class="bot-status">Powered by AI</div></div>
        <button class="chatbot-close" id="chatbot-close-btn" aria-label="Close chatbot"><svg width="18" height="18" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
    </div>
    <div class="chatbot-body"><div id="chronolms-chatbot-inner"></div></div>
</div>

<?php echo theme_chronolms_standalone_end_html(); ?>
</body>
</html>
