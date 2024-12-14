<?php
session_start();
date_default_timezone_set('UTC'); // Adjust timezone as needed.

$db_file = __DIR__ . '/db.txt';

// Handle AJAX actions
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    if ($action === 'start') {
        // Start a new work session
        $_SESSION['work_start'] = (new DateTime())->format('Y-m-d H:i:s');
        echo json_encode(['status' => 'started', 'start_time' => $_SESSION['work_start']]);
        exit;
    } elseif ($action === 'stop') {
        // Stop the current work session
        if (isset($_SESSION['work_start'])) {
            $start_str = $_SESSION['work_start'];
            $start = DateTime::createFromFormat('Y-m-d H:i:s', $start_str);
            $end = new DateTime();
            $diff = $start->diff($end);
            $duration = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
            $duration = round($duration, 2);

            $date = $end->format('Y-m-d');
            $start_time = $start->format('H:i:s');
            $end_time = $end->format('H:i:s');

            $line = $date . "|" . $start_time . "|" . $end_time . "|" . $duration . "\n";
            file_put_contents($db_file, $line, FILE_APPEND);

            unset($_SESSION['work_start']);
            echo json_encode(['status' => 'stopped', 'duration' => $duration]);
        } else {
            echo json_encode(['status' => 'no_session']);
        }
        exit;
    } elseif ($action === 'stats') {
        $entries = parse_entries($db_file);
        $averages = compute_averages($entries);
        echo json_encode($averages);
        exit;
    } elseif ($action === '7days') {
        $entries = parse_entries($db_file);
        $last7 = get_last_7_days_entries($entries);
        echo json_encode($last7);
        exit;
    } elseif ($action === 'month') {
        $entries = parse_entries($db_file);
        $month_data = get_month_data($entries);
        echo json_encode($month_data);
        exit;
    }
    exit;
}

// Helper functions

function parse_entries($filename) {
    if (!file_exists($filename)) return [];
    $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $entries = [];
    foreach ($lines as $line) {
        // Format: YYYY-MM-DD|start_time|end_time|duration
        $parts = explode('|', $line);
        if (count($parts) == 4) {
            $entries[] = [
                'date' => $parts[0],
                'startTime' => $parts[1],
                'endTime' => $parts[2],
                'duration' => (float)$parts[3]
            ];
        }
    }
    return $entries;
}

function compute_averages($entries) {
    $today = new DateTime();
    $currentYear = $today->format('Y');
    $currentMonth = $today->format('m');
    $currentDay = $today->format('Y-m-d');
    $weekStart = (new DateTime())->modify('-6 days'); // last 7 days including today

    $day_durations = [];
    $week_durations = [];
    $month_durations = [];
    $year_durations = [];

    foreach ($entries as $e) {
        $entryDate = DateTime::createFromFormat('Y-m-d', $e['date']);
        $duration = $e['duration'];

        // Daily
        if ($entryDate->format('Y-m-d') == $currentDay) {
            $day_durations[] = $duration;
        }
        // Weekly
        if ($entryDate >= $weekStart && $entryDate <= $today) {
            $week_durations[] = $duration;
        }
        // Monthly
        if ($entryDate->format('Y') == $currentYear && $entryDate->format('m') == $currentMonth) {
            $month_durations[] = $duration;
        }
        // Yearly
        if ($entryDate->format('Y') == $currentYear) {
            $year_durations[] = $duration;
        }
    }

    return [
        'daily' => count($day_durations) > 0 ? round(array_sum($day_durations)/count($day_durations),2) : 0,
        'weekly' => count($week_durations) > 0 ? round(array_sum($week_durations)/count($week_durations),2) : 0,
        'monthly' => count($month_durations) > 0 ? round(array_sum($month_durations)/count($month_durations),2) : 0,
        'yearly' => count($year_durations) > 0 ? round(array_sum($year_durations)/count($year_durations),2) : 0,
    ];
}

function get_last_7_days_entries($entries) {
    $today = new DateTime();
    $weekStart = (new DateTime())->modify('-6 days');

    $result = [];
    foreach ($entries as $e) {
        $entryDate = DateTime::createFromFormat('Y-m-d', $e['date']);
        if ($entryDate >= $weekStart && $entryDate <= $today) {
            $result[] = $e;
        }
    }
    return $result;
}

function get_month_data($entries) {
    $today = new DateTime();
    $currentYear = $today->format('Y');
    $currentMonth = $today->format('m');

    $month_data = [];
    foreach ($entries as $e) {
        $entryDate = DateTime::createFromFormat('Y-m-d', $e['date']);
        if ($entryDate->format('Y') == $currentYear && $entryDate->format('m') == $currentMonth) {
            $d = $entryDate->format('d');
            if (!isset($month_data[$d])) {
                $month_data[$d] = 0;
            }
            $month_data[$d] += $e['duration'];
        }
    }
    return $month_data;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Work Timer</title>
<style>
    body {
        font-family: Arial, sans-serif;
        background: #f7f7f7;
        margin: 0;
        padding: 0;
        text-align: center;
    }

    .container {
        max-width: 600px;
        margin: 50px auto;
        padding: 20px;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    h1 {
        margin-bottom: 20px;
    }

    .nav {
        margin-bottom: 20px;
    }

    .nav button {
        background: #007BFF;
        color: #fff;
        border: none;
        padding: 10px 15px;
        margin: 0 5px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    .nav button.active {
        background: #0056b3;
    }

    /* Daily View */
    #dailyView {
        margin-bottom: 20px;
    }

    #dailyView .timer-controls button {
        background: #28a745;
        color: #fff;
        border: none;
        padding: 10px 15px;
        margin: 0 5px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
    }

    #dailyView .timer-controls .stop-btn {
        background: #dc3545;
    }

    .averages {
        text-align: left;
        margin-top: 20px;
    }

    .averages div {
        margin: 5px 0;
    }

    /* 7-Day View */
    #sevenDayView table {
        width: 100%;
        border-collapse: collapse;
    }

    #sevenDayView table, #sevenDayView th, #sevenDayView td {
        border: 1px solid #ccc;
    }

    #sevenDayView th, #sevenDayView td {
        padding: 8px;
        text-align: center;
    }

    /* Monthly View (Calendar) */
    #monthlyView .calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        grid-gap: 5px;
        margin-top: 20px;
    }

    #monthlyView .calendar div {
        background: #e9e9e9;
        padding: 10px;
        border-radius: 4px;
        min-height: 60px;
        position: relative;
    }

    #monthlyView .calendar .day-label {
        font-weight: bold;
        background: none;
        padding: 0;
        min-height: auto;
    }

    #monthlyView .calendar .day-cell {
        text-align: left;
    }

    #monthlyView .calendar .day-num {
        font-weight: bold;
        position: absolute;
        top: 5px;
        right: 5px;
        font-size: 12px;
    }

    #monthlyView .calendar .day-hours {
        margin-top: 20px;
        font-size: 14px;
    }

    .hidden {
        display: none;
    }
</style>
</head>
<body>
<div class="container">
    <h1>Work Timer</h1>
    <div class="nav">
        <button id="dailyBtn" class="active">Daily View</button>
        <button id="sevenDayBtn">7-Day View</button>
        <button id="monthlyBtn">Monthly View</button>
    </div>

    <!-- Daily View -->
    <div id="dailyView">
        <div class="timer-controls">
            <button id="startBtn">Start Work</button>
            <button id="stopBtn" class="stop-btn">Stop Work</button>
        </div>
        <div class="averages">
            <h2>Today's Stats</h2>
            <div><strong>Daily Average:</strong> <span id="dailyAvg">--</span> hours</div>
            <div><strong>Weekly Average:</strong> <span id="weeklyAvg">--</span> hours</div>
            <div><strong>Monthly Average:</strong> <span id="monthlyAvg">--</span> hours</div>
            <div><strong>Yearly Average:</strong> <span id="yearlyAvg">--</span> hours</div>
        </div>
    </div>

    <!-- 7-Day View -->
    <div id="sevenDayView" class="hidden">
        <h2>Last 7 Days</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Duration (hrs)</th>
                </tr>
            </thead>
            <tbody id="sevenDayTableBody"></tbody>
        </table>
        <div class="averages">
            <h3>7-Day Average</h3>
            <div><strong>Weekly Average:</strong> <span id="weeklyAvg7">--</span> hours</div>
        </div>
    </div>

    <!-- Monthly View -->
    <div id="monthlyView" class="hidden">
        <h2>This Month</h2>
        <div><strong>Monthly Average:</strong> <span id="monthlyAvgCalendar">--</span> hours</div>
        <div class="calendar" id="calendarGrid"></div>
    </div>
</div>

<script>
const dailyView = document.getElementById('dailyView');
const sevenDayView = document.getElementById('sevenDayView');
const monthlyView = document.getElementById('monthlyView');
const dailyBtn = document.getElementById('dailyBtn');
const sevenDayBtn = document.getElementById('sevenDayBtn');
const monthlyBtn = document.getElementById('monthlyBtn');

dailyBtn.addEventListener('click', () => { showView('daily'); });
sevenDayBtn.addEventListener('click', () => { showView('7day'); });
monthlyBtn.addEventListener('click', () => { showView('month'); });

function showView(view) {
    dailyView.classList.add('hidden');
    sevenDayView.classList.add('hidden');
    monthlyView.classList.add('hidden');
    dailyBtn.classList.remove('active');
    sevenDayBtn.classList.remove('active');
    monthlyBtn.classList.remove('active');

    if (view === 'daily') {
        dailyView.classList.remove('hidden');
        dailyBtn.classList.add('active');
        updateStats();
    } else if (view === '7day') {
        sevenDayView.classList.remove('hidden');
        sevenDayBtn.classList.add('active');
        load7Days();
    } else if (view === 'month') {
        monthlyView.classList.remove('hidden');
        monthlyBtn.classList.add('active');
        loadMonthView();
    }
}

document.getElementById('startBtn').addEventListener('click', () => {
    fetch('?action=start')
    .then(r=>r.json())
    .then(d=>{
        console.log('Work started at: ' + d.start_time);
    });
});

document.getElementById('stopBtn').addEventListener('click', () => {
    fetch('?action=stop')
    .then(r=>r.json())
    .then(d=>{
        if (d.status === 'stopped') {
            console.log('Work stopped. Duration: ' + d.duration + ' hours');
            updateStats();
        } else {
            console.log('No session to stop');
        }
    });
});

function updateStats() {
    fetch('?action=stats')
    .then(r=>r.json())
    .then(data => {
        document.getElementById('dailyAvg').textContent = data.daily;
        document.getElementById('weeklyAvg').textContent = data.weekly;
        document.getElementById('monthlyAvg').textContent = data.monthly;
        document.getElementById('yearlyAvg').textContent = data.yearly;
    });
}

function load7Days() {
    fetch('?action=7days')
    .then(r=>r.json())
    .then(entries => {
        const tbody = document.getElementById('sevenDayTableBody');
        tbody.innerHTML = '';
        let total = 0;
        entries.forEach(e=>{
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${e.date}</td><td>${e.startTime}</td><td>${e.endTime}</td><td>${e.duration}</td>`;
            tbody.appendChild(tr);
            total += e.duration;
        });
        let avg = entries.length > 0 ? (total/entries.length).toFixed(2) : 0;
        document.getElementById('weeklyAvg7').textContent = avg;
    });
}

function loadMonthView() {
    fetch('?action=month')
    .then(r=>r.json())
    .then(data => {
        const calendarGrid = document.getElementById('calendarGrid');
        calendarGrid.innerHTML = '';

        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth(); // 0-based
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month+1, 0);
        const daysInMonth = lastDay.getDate();

        // Day labels
        const dayLabels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
        dayLabels.forEach(d=>{
            const div = document.createElement('div');
            div.textContent = d;
            div.classList.add('day-label');
            calendarGrid.appendChild(div);
        });

        // Empty cells before the first day
        for (let i=0; i<firstDay.getDay(); i++) {
            const emptyDiv = document.createElement('div');
            emptyDiv.classList.add('day-cell');
            calendarGrid.appendChild(emptyDiv);
        }

        let totalHours = 0;
        let count = 0;

        for (let d=1; d<=daysInMonth; d++) {
            const cell = document.createElement('div');
            cell.classList.add('day-cell');
            const dayNum = document.createElement('div');
            dayNum.classList.add('day-num');
            dayNum.textContent = d;
            cell.appendChild(dayNum);

            let hours = data[d < 10 ? '0'+d : ''+d] || 0;
            if (hours > 0) {
                const hrsDiv = document.createElement('div');
                hrsDiv.classList.add('day-hours');
                hrsDiv.textContent = hours + ' hrs';
                cell.appendChild(hrsDiv);
                totalHours += hours;
                count++;
            }

            calendarGrid.appendChild(cell);
        }

        let monthlyAvg = count > 0 ? (totalHours / count).toFixed(2) : 0;
        document.getElementById('monthlyAvgCalendar').textContent = monthlyAvg;
    });
}

// On load, show daily and load stats
document.addEventListener('DOMContentLoaded', ()=>{
    updateStats();
});
</script>
</body>
</html>
