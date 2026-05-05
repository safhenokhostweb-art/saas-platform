<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('support_tickets', 'ticket_number')) {
            return;
        }

        $tickets = DB::table('support_tickets')
            ->whereNull('ticket_number')
            ->orderBy('created_at')
            ->get();

        $yearSeq = [];
        foreach ($tickets as $ticket) {
            $year = date('Y', strtotime($ticket->created_at));
            $yearSeq[$year] = ($yearSeq[$year] ?? 0) + 1;
            $number = sprintf('ST-%s-%05d', $year, $yearSeq[$year]);
            DB::table('support_tickets')->where('id', $ticket->id)->update(['ticket_number' => $number]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
