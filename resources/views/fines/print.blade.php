@php
    $unpaidTotal = $students->sum('unpaid');
    // One fixed-width text line per student: dompdf needs ~0.2 MB per table row and ran out of memory on big lists
    $line = fn ($id, $name, $status, $amount) => str_pad($id, 15) . mb_str_pad(mb_strimwidth($name, 0, 40, '…'), 42) . str_pad($status, 8) . mb_str_pad($amount, 13, ' ', STR_PAD_LEFT);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fines, {{ $department->code }}, {{ now()->format('Y-m-d') }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font: 12px/1.4 'DejaVu Sans', sans-serif; color: #111; margin: 0; } /* DejaVu: dompdf's bundled font with ₱ and ñ */
        h1 { font-size: 18px; margin: 0 0 2px; }
        p { margin: 0 0 12px; color: #444; }
        pre { font: 9.5px/1.7 'DejaVu Sans Mono', monospace; margin: 0; }
        .head { font-weight: bold; border-bottom: 1.5px solid #111; }
    </style>
</head>
<body>
    <h1>Fines, {{ $department->name }}</h1>
    <p>Exported {{ now()->format('F j, Y g:i A') }}. {{ $students->count() }} {{ \Illuminate\Support\Str::plural('student', $students->count()) }} still owe, ₱{{ number_format($unpaidTotal, 2) }} in total.</p>
    <pre class="head">{{ $line('Student ID', 'Name', 'Status', 'Amount') }}</pre>
    <pre>@foreach($students as $s){{ $line($s->user->username, $s->user->name, 'Owes', '₱' . number_format($s->unpaid, 2)) }}
@endforeach</pre>
</body>
</html>
