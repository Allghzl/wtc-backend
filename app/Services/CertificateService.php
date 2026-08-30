<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Profile;
use App\Models\Track;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    public function __construct(
        protected GradeCalculatorService $gradeCalculator
    ) {}

    /**
     * Issue a new certificate for a profile/track.
     * Throws if one already exists.
     */
    public function issue(Profile $profile, Track $track): Certificate
    {
        $existing = Certificate::where('profile_id', $profile->id)
            ->where('track_id', $track->id)
            ->first();

        if ($existing) {
            throw new \RuntimeException('Certificate already issued for this profile and track.');
        }

        $score = $this->gradeCalculator->calculateTrackGrade($profile, $track);
        $grade = Certificate::gradeLabel($score);

        $cert = Certificate::create([
            'profile_id'         => $profile->id,
            'track_id'           => $track->id,
            'certificate_number' => Str::uuid()->toString(),
            'grade'              => $grade,
            'grade_score'        => $score,
            'status'             => 'issued',
            'issued_at'          => now(),
        ]);

        $this->generatePdf($cert);

        return $cert->fresh();
    }

    /**
     * Generate and persist a PDF (or HTML fallback) for the certificate.
     */
    public function generatePdf(Certificate $cert): void
    {
        $cert->loadMissing(['profile', 'track']);

        $template = CertificateTemplate::where('is_active', true)->first();

        $studentName = $cert->profile->display_name ?? $cert->profile->nickname ?? 'Student';
        $trackTitle  = $cert->track->title ?? 'Track';
        $grade       = $cert->grade;
        $score       = number_format($cert->grade_score, 1);
        $date        = $cert->issued_at ? $cert->issued_at->format('d F Y') : now()->format('d F Y');
        $certNumber  = $cert->certificate_number;

        if ($template) {
            $html = $template->html_template;
            $css  = $template->css_styles ?? '';

            $html = str_replace(
                ['{{student_name}}', '{{track_title}}', '{{grade}}', '{{score}}', '{{date}}', '{{certificate_number}}'],
                [$studentName,       $trackTitle,       $grade,      $score,      $date,      $certNumber],
                $html
            );

            if ($css) {
                $html = str_replace('</head>', "<style>{$css}</style></head>", $html);
            }

            if ($template->background_url) {
                $html = str_replace('{{background_url}}', $template->background_url, $html);
            }
        } else {
            $html = $this->defaultHtml($studentName, $trackTitle, $grade, $score, $date, $certNumber);
        }

        $pdfPath = "certificates/{$certNumber}.pdf";

        // Generate PDF using DomPDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
            ->setPaper('a4', 'landscape')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);

        // Store on S3 / object storage
        Storage::disk('s3')->put($pdfPath, $pdf->output(), 'private');
        $cert->update(['pdf_path' => $pdfPath]);
    }

    /**
     * Compare current grade to issued grade; mark update_available if improved.
     */
    public function checkForUpgrade(Profile $profile, Track $track): void
    {
        $cert = Certificate::where('profile_id', $profile->id)
            ->where('track_id', $track->id)
            ->first();

        if (!$cert) {
            return;
        }

        $newScore = $this->gradeCalculator->calculateTrackGrade($profile, $track);
        $newGrade = Certificate::gradeLabel($newScore);

        // Mark for upgrade only when grade has improved
        if ($newScore > $cert->grade_score) {
            $cert->update(['status' => 'update_available']);
        }
    }

    /**
     * Recalculate grade, regenerate PDF, reset status to issued.
     */
    public function regenerate(Certificate $cert): Certificate
    {
        $cert->loadMissing(['profile', 'track']);

        $score = $this->gradeCalculator->calculateTrackGrade($cert->profile, $cert->track);
        $grade = Certificate::gradeLabel($score);

        $cert->update([
            'grade'       => $grade,
            'grade_score' => $score,
            'status'      => 'issued',
            'issued_at'   => now(),
        ]);

        $this->generatePdf($cert);

        return $cert->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    private function defaultHtml(
        string $studentName,
        string $trackTitle,
        string $grade,
        string $score,
        string $date,
        string $certNumber
    ): string {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Certificate of Completion</title>
<style>
  body { font-family: Georgia, serif; text-align: center; padding: 60px; background: #fff; color: #222; }
  .border { border: 8px double #b8860b; padding: 40px; display: inline-block; width: 80%; }
  h1 { font-size: 2.4em; color: #b8860b; margin-bottom: 0; }
  h2 { font-size: 1.6em; margin-top: 10px; }
  p  { font-size: 1.1em; }
  .grade { font-size: 3em; color: #b8860b; font-weight: bold; }
  .cert-num { font-size: 0.75em; color: #888; margin-top: 30px; }
</style>
</head>
<body>
  <div class="border">
    <h1>Certificate of Completion</h1>
    <p>This certifies that</p>
    <h2>{$studentName}</h2>
    <p>has successfully completed the track</p>
    <h2>{$trackTitle}</h2>
    <p>with a final grade of</p>
    <div class="grade">{$grade}</div>
    <p>Score: {$score} / 100</p>
    <p>Issued on {$date}</p>
    <p class="cert-num">Certificate No: {$certNumber}</p>
  </div>
</body>
</html>
HTML;
    }
}
