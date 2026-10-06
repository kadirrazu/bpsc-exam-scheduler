<?php

namespace App\Services\Scheduler;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ScheduleExport
{
    public function response(string $format, Collection $schedules, array $filters)
    {
        $filename = 'BPSC-Schedules-'.$filters['from'].'-to-'.$filters['to'].'-'.now()->format('Ymd-His');
        $details = ['report' => 'exam_schedule_list', 'report_name' => 'Exam Schedule List', 'format' => $format, 'filters' => $filters, 'row_count' => $schedules->count(), 'locale' => app()->getLocale(), 'generated_at' => now()->toIso8601String()];
        if ($format === 'print') {
            $reportToken = Crypt::encryptString(json_encode($details + ['actor_id' => request()->user()->id, 'expires_at' => now()->addMinutes(30)->timestamp], JSON_THROW_ON_ERROR));
            // Render first: a Blade failure must never be reported as a successful report view.
            $html = view('schedules.report', compact('schedules', 'filters', 'reportToken') + ['printing' => true])->render();
            app(Audit::class)->record('report.viewed', $details);

            return response($html);
        }
        if ($format === 'pdf') {
            $bytes = app(SchedulePdf::class)->render(view('schedules.report', compact('schedules', 'filters') + ['printing' => false])->render());
            app(Audit::class)->record('report.generated', $details + ['filename' => $filename.'.pdf']);

            return response($bytes, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"']);
        }
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Exam Schedules');
        $headers = array_map(fn ($s) => __($s), ['#', 'Date', 'Exam Type', 'Post Name', 'Post Code', 'Advertisement Number', 'Advertisement Year', 'Unit', 'Start', 'End', 'Candidates', 'Centers', 'Boards', 'Status', 'Notes/Remarks', 'Ministry/Organization']);
        $sheet->setCellValue('A1', __('Bangladesh Public Service Commission (BPSC)'));
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A2', __('Exam Schedules').': '.$filters['from'].' — '.$filters['to']);
        $sheet->mergeCells('A2:P2');
        $sheet->fromArray($headers, null, 'A4');
        foreach ($schedules as $i => $s) {
            $row = [$i + 1, $s->exam_date->format('Y-m-d'), $s->typeLabel(), $s->post_name ?? $s->title, $s->reference, $s->advertisement_number, $s->advertisement_year, __($s->unit), $s->start_time ? substr($s->start_time, 0, 5) : '', $s->end_time ? substr($s->end_time, 0, 5) : '', $s->candidate_count, $s->center_count, $s->board_count, __(config('scheduler.statuses.'.$s->status)), $s->notes, $s->ministry];
            $sheet->getStyle('N'.($i + 5))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(config('scheduler.status_colors.'.$s->status, 'F1F3F5'));
            foreach ($row as $col => $value) {
                $sheet->setCellValueExplicit([$col + 1, $i + 5], $value ?? '', is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
        }
        $last = max(4, $schedules->count() + 4);
        $sheet->getStyle('A1:P4')->getFont()->setBold(true);
        $sheet->getStyle('A4:P'.$last)->getAlignment()->setWrapText(true);
        $sheet->getStyle('A4:P'.$last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setWidth(in_array($col, ['D', 'O', 'P']) ? 35 : 16);
        }
        $sheet->freezePane('A5');
        $sheet->setAutoFilter('A4:P'.$last);
        // Complete XLSX generation before logging success or sending any bytes. Never log failed generation as success.
        $temp = tmpfile();
        if (! $temp) {
            throw new \RuntimeException('Cannot create spreadsheet temporary file.');
        }
        try {
            (new Xlsx($book))->save(stream_get_meta_data($temp)['uri']);
            rewind($temp);
            $bytes = stream_get_contents($temp);
        } finally {
            fclose($temp);
            $book->disconnectWorksheets();
        }
        app(Audit::class)->record('report.generated', $details + ['filename' => $filename.'.xlsx']);

        return response($bytes, 200, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Content-Disposition' => 'attachment; filename="'.$filename.'.xlsx"']);
    }
}
