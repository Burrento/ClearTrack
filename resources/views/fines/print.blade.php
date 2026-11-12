@php $unpaidTotal = $students->sum('unpaid'); @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fines, {{ $department->code }}, {{ now()->format('Y-m-d') }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        body { font: 11px/1.4 system-ui, sans-serif; color: #111; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        p { margin: 0 0 12px; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 5px 6px; vertical-align: top; }
        thead th { border-bottom: 2px solid #111; }
        thead { display: table-header-group; }
        tbody td { border-bottom: 1px solid #ccc; }
        tr { break-inside: avoid; }
        .num { text-align: right; white-space: nowrap; }
        .owes { color: #b42318; }
        .no-print { margin-bottom: 12px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print"><button onclick="window.print()">Save as PDF / Print</button></div>
    <h1>Fines, {{ $department->name }}</h1>
    <p>Exported {{ now()->format('F j, Y g:i A') }}. {{ $students->count() }} {{ \Illuminate\Support\Str::plural('student', $students->count()) }} still owe, ₱{{ number_format($unpaidTotal, 2) }} in total.</p>
    <table>
        <thead>
            <tr><th>Student ID</th><th>Name</th><th>Status</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            @foreach($students as $s)
                <tr>
                    <td>{{ $s->user->username }}</td>
                    <td>{{ $s->user->name }}</td>
                    <td @class(['owes' => $s->unpaid > 0])>{{ $s->unpaid > 0 ? 'Owes' : 'Cleared' }}</td>
                    <td class="num">₱{{ number_format($s->unpaid, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
