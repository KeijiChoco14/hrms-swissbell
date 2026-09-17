<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PerformanceScore;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AiAssistantService
{
    /**
     * Generate structured AI Executive Briefing and Health Index.
     */
    public function generateExecutiveSummary(): array
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        // 1. Attendance Metrics
        $totalEmployees = Employee::count();
        $workDays = max($today->diffInWeekdays($monthStart) + 1, 1);
        $totalPossibleAttendance = $totalEmployees * $workDays;
        $totalPresent = Attendance::whereBetween('date', [$monthStart, $today])
            ->whereIn('status', ['Present', 'Late'])
            ->count();
        $lateCount = Attendance::whereBetween('date', [$monthStart, $today])
            ->where('status', 'Late')
            ->count();

        $attendanceRate = $totalPossibleAttendance > 0
            ? round(($totalPresent / $totalPossibleAttendance) * 100, 1)
            : 100;

        // 2. Task & Project Metrics
        $totalActiveTasks = Task::where('status', '!=', 'Done')->count();
        $completedTasksMonth = Task::where('status', 'Done')
            ->whereBetween('updated_at', [$monthStart, now()])
            ->count();
        $overdueTasksCount = Task::where('deadline', '<', now())
            ->where('status', '!=', 'Done')
            ->count();
        $reviewTasksCount = Task::where('status', 'Review')->count();
        $activeProjectsCount = Project::where('status', 'Active')->count();

        // Task completion rate calculation
        $allTasksCount = Task::count();
        $completedAllTasksCount = Task::where('status', 'Done')->count();
        $overallCompletionRate = $allTasksCount > 0
            ? round(($completedAllTasksCount / $allTasksCount) * 100, 1)
            : 100;

        // 3. Operational Health Score Calculation (0 - 100)
        // Attendance weight: 35%, Task completion weight: 35%, Overdue penalty: 20%, Pending items: 10%
        $overduePenalty = min($overdueTasksCount * 5, 30);
        $healthScore = round(
            ($attendanceRate * 0.35) +
            ($overallCompletionRate * 0.35) +
            (max(100 - $overduePenalty, 0) * 0.20) +
            (100 * 0.10)
        );
        $healthScore = min(max($healthScore, 0), 100);

        $healthStatus = 'Excellent';
        $healthColor = 'emerald';
        if ($healthScore < 70) {
            $healthStatus = 'Needs Attention';
            $healthColor = 'rose';
        } elseif ($healthScore < 85) {
            $healthStatus = 'Good';
            $healthColor = 'amber';
        }

        // 4. Pending Requests
        $pendingLeaves = LeaveRequest::where('status', 'Pending')->count();
        $pendingOvertime = OvertimeRequest::where('status', 'Pending')->count();

        // 5. Top Performer Recommendation (Weighted Calculation)
        $topPerformer = $this->calculateTopPerformer();

        // 6. Department Health Breakdown
        $departmentBreakdown = Department::withCount('employees')
            ->with(['employees' => function ($q) {
                $q->withCount([
                    'tasks as active_tasks_count' => function ($query) {
                        $query->where('status', '!=', 'Done');
                    },
                    'tasks as completed_tasks_count' => function ($query) {
                        $query->where('status', 'Done');
                    },
                ]);
            }])
            ->get()
            ->map(function ($dept) {
                $activeTasks = $dept->employees->sum('active_tasks_count');
                $completedTasks = $dept->employees->sum('completed_tasks_count');
                $total = $activeTasks + $completedTasks;
                $rate = $total > 0 ? round(($completedTasks / $total) * 100) : 100;
                return [
                    'id' => $dept->id,
                    'name' => $dept->name,
                    'employees_count' => $dept->employees_count,
                    'active_tasks' => $activeTasks,
                    'completed_tasks' => $completedTasks,
                    'completion_rate' => $rate,
                ];
            });

        // 7. Natural Language AI Executive Briefing Narrative
        $briefingText = $this->buildBriefingNarrative(
            $healthStatus,
            $healthScore,
            $attendanceRate,
            $lateCount,
            $completedTasksMonth,
            $overdueTasksCount,
            $pendingLeaves,
            $pendingOvertime,
            $topPerformer
        );

        // 8. Key Risk Alerts
        $alerts = [];
        if ($overdueTasksCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Tugas Overdue Terdeteksi',
                'message' => "Terdapat {$overdueTasksCount} tugas yang melebihi deadline. Disarankan koordinasi dengan Supervisor terkait.",
            ];
        }
        if ($pendingLeaves + $pendingOvertime > 5) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Persetujuan Menunggu (Pending Approvals)',
                'message' => "Ada {$pendingLeaves} pengajuan cuti dan {$pendingOvertime} lembur yang perlu ditinjau oleh HRD/GM.",
            ];
        }
        if ($attendanceRate < 85) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Tingkat Kehadiran Turun',
                'message' => "Tingkat kehadiran bulan ini berada di angka {$attendanceRate}%. Perlu peninjauan kedisiplinan shift.",
            ];
        }
        if (empty($alerts)) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Operasional Stabil',
                'message' => 'Seluruh indikator operasional hotel berada dalam kondisi optimal tanpa hambatan kritis.',
            ];
        }

        return [
            'healthScore' => $healthScore,
            'healthStatus' => $healthStatus,
            'healthColor' => $healthColor,
            'attendanceRate' => $attendanceRate,
            'completedTasksMonth' => $completedTasksMonth,
            'overdueTasksCount' => $overdueTasksCount,
            'reviewTasksCount' => $reviewTasksCount,
            'pendingApprovals' => $pendingLeaves + $pendingOvertime,
            'briefingNarrative' => $briefingText,
            'topPerformer' => $topPerformer,
            'alerts' => $alerts,
            'departmentBreakdown' => $departmentBreakdown,
            'generatedAt' => now()->translatedFormat('d F Y H:i'),
        ];
    }

    /**
     * Process interactive natural language executive prompts.
     */
    public function answerExecutiveQuery(string $query): array
    {
        $queryLower = mb_strtolower(trim($query));
        $summary = $this->generateExecutiveSummary();

        // Topic 1: Employee of the Month / Top Performers
        if (str_contains($queryLower, 'terbaik') || str_contains($queryLower, 'performa') || str_contains($queryLower, 'employee of the month') || str_contains($queryLower, 'karyawan')) {
            $top = $summary['topPerformer'];
            if ($top) {
                $response = "⭐ **Rekomendasi Karyawan Terbaik (Employee of the Month):**\n\n" .
                    "Karyawan terbaik yang direkomendasikan sistem adalah **{$top['name']}** ({$top['position']} - {$top['department']}) dengan Skor Performa **{$top['compositeScore']}/100**.\n\n" .
                    "📌 **Highlights Pencapaian:**\n" .
                    "• Menyelesaikan **{$top['completedTasks']} tugas** dengan sukses.\n" .
                    "• Skor Penilaian Supervisor: **{$top['supervisorRating']}/100**.\n" .
                    "• Kedisiplinan Kehadiran: **{$top['attendanceRate']}%**.\n\n" .
                    "💡 *Saran Manajemen*: Berikan apresiasi atau sertifikat 'Employee of the Month' pada briefing bulanan berikutnya.";
            } else {
                $response = "Belum ada data indikator kinerja yang cukup untuk menentukan rekomendasi karyawan terbaik bulan ini.";
            }

            return [
                'query' => $query,
                'category' => 'top_performer',
                'response' => $response,
                'data' => $summary['topPerformer'],
            ];
        }

        // Topic 2: Department Analysis
        if (str_contains($queryLower, 'departemen') || str_contains($queryLower, 'divisi') || str_contains($queryLower, 'department')) {
            $depts = $summary['departmentBreakdown'];
            $deptList = '';
            foreach ($depts as $d) {
                $deptList .= "• **{$d['name']}**: {$d['completed_tasks']} Selesai, {$d['active_tasks']} Aktif (Tingkat Penyelesaian: {$d['completion_rate']}%)\n";
            }

            $response = "📊 **Analisis Kesehatan Operasional per Departemen:**\n\n" .
                $deptList . "\n" .
                "💡 *Rekomendasi*: Fokuskan alokasi sumber daya ke departemen dengan beban *active tasks* yang masih tinggi.";

            return [
                'query' => $query,
                'category' => 'department_analysis',
                'response' => $response,
                'data' => $depts,
            ];
        }

        // Topic 3: Risk Alerts / Overdue Tasks
        if (str_contains($queryLower, 'resiko') || str_contains($queryLower, 'risiko') || str_contains($queryLower, 'terlambat') || str_contains($queryLower, 'overdue') || str_contains($queryLower, 'masalah')) {
            $alerts = $summary['alerts'];
            $alertText = '';
            foreach ($alerts as $a) {
                $alertText .= "• **{$a['title']}**: {$a['message']}\n";
            }

            $response = "🚨 **Peringatan Risiko Operasional & Task Delay:**\n\n" .
                "Saat ini terdapat **{$summary['overdueTasksCount']} tugas overdue** dan **{$summary['pendingApprovals']} pengajuan menunggu approval**.\n\n" .
                "📋 **Rincian Alert Sistem:**\n" . $alertText . "\n" .
                "💡 *Tindakan Disarankan*: Instruksikan Head of Department terkait untuk mempercepat *review* dan verifikasi tugas.";

            return [
                'query' => $query,
                'category' => 'risk_alerts',
                'response' => $response,
                'data' => $alerts,
            ];
        }

        // Topic 4: General Executive Briefing (Default)
        $response = "🤖 **Rangkuman Eksekutif AI (Swiss-Belinn HRMS):**\n\n" .
            $summary['briefingNarrative'] . "\n\n" .
            "📌 **Status Ringkas:**\n" .
            "• Indeks Kesehatan Operasional: **{$summary['healthScore']}/100 ({$summary['healthStatus']})**\n" .
            "• Tingkat Kehadiran Bulanan: **{$summary['attendanceRate']}%**\n" .
            "• Tugas Selesai Bulan Ini: **{$summary['completedTasksMonth']} tugas**\n" .
            "• Tugas Melebihi Deadline: **{$summary['overdueTasksCount']} tugas**\n\n" .
            "Silakan pilih atau tanyakan detail lebih lanjut mengenai *Performa Karyawan*, *Analisis Departemen*, atau *Alert Risiko*.";

        return [
            'query' => $query,
            'category' => 'executive_summary',
            'response' => $response,
            'data' => $summary,
        ];
    }

    /**
     * Calculate Top Performer recommendation using multi-criteria weighted algorithm.
     */
    private function calculateTopPerformer(): ?array
    {
        $employees = Employee::with(['user:id,name,email,profile_photo_path', 'department:id,name', 'position:id,name'])
            ->get();

        if ($employees->isEmpty()) {
            return null;
        }

        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $scoredEmployees = $employees->map(function ($emp) use ($monthStart, $today) {
            // Completed tasks count
            $completedTasks = Task::whereHas('assignees', fn ($q) => $q->where('employee_id', $emp->id))
                ->where('status', 'Done')
                ->count();

            // Total assigned tasks
            $totalTasks = Task::whereHas('assignees', fn ($q) => $q->where('employee_id', $emp->id))->count();

            $taskScore = $totalTasks > 0 ? min(round(($completedTasks / $totalTasks) * 100), 100) : 75;

            // Attendance score this month
            $totalDays = max($today->diffInWeekdays($monthStart) + 1, 1);
            $presentDays = Attendance::where('employee_id', $emp->id)
                ->whereBetween('date', [$monthStart, $today])
                ->whereIn('status', ['Present', 'Late'])
                ->count();
            $attendanceRate = min(round(($presentDays / $totalDays) * 100), 100);

            // Latest supervisor performance score
            $latestPerf = PerformanceScore::where('employee_id', $emp->id)
                ->orderBy('created_at', 'desc')
                ->first();
            $supervisorScore = $latestPerf ? floatval($latestPerf->final_score) : 80;

            // Composite score: 40% Supervisor Assessment + 35% Task Completion + 25% Attendance
            $compositeScore = round(($supervisorScore * 0.40) + ($taskScore * 0.35) + ($attendanceRate * 0.25), 1);

            return [
                'id' => $emp->id,
                'name' => $emp->user?->name ?? 'N/A',
                'photo' => $emp->user?->profile_photo_path,
                'department' => $emp->department?->name ?? 'Umum',
                'position' => $emp->position?->name ?? 'Staff',
                'compositeScore' => $compositeScore,
                'supervisorRating' => round($supervisorScore, 1),
                'completedTasks' => $completedTasks,
                'attendanceRate' => $attendanceRate,
            ];
        });

        return $scoredEmployees->sortByDesc('compositeScore')->first();
    }

    /**
     * Build natural language briefing narrative.
     */
    private function buildBriefingNarrative(
        string $status,
        int $score,
        float $attendanceRate,
        int $lateCount,
        int $completedTasks,
        int $overdueCount,
        int $pendingLeaves,
        int $pendingOvertime,
        ?array $topPerformer
    ): string {
        $intro = "Operasional Swiss-Belinn Pekanbaru berada dalam status **{$status}** dengan Indeks Kesehatan **{$score}/100**.";

        $attendanceText = " Tingkat kehadiran staf bulan ini mencapai **{$attendanceRate}%**";
        if ($lateCount > 0) {
            $attendanceText .= " dengan pencatatan keterlambatan sebanyak **{$lateCount} kali**.";
        } else {
            $attendanceText .= " dengan tingkat kedisiplinan waktu yang sangat baik.";
        }

        $taskText = " Di bidang manajemen kerja, sebanyak **{$completedTasks} tugas** telah berhasil diselesaikan bulan ini.";
        if ($overdueCount > 0) {
            $taskText .= " Namun, perhatian diperlukan untuk **{$overdueCount} tugas** yang melebihi tenggat waktu.";
        } else {
            $taskText .= " Seluruh target tugas terkelola dengan baik tanpa ada *overdue*.";
        }

        $approvalText = "";
        if ($pendingLeaves + $pendingOvertime > 0) {
            $approvalText = " Terdapat total **" . ($pendingLeaves + $pendingOvertime) . " pengajuan** (cuti/lembur) yang menunggu peninjauan Anda.";
        }

        $topText = "";
        if ($topPerformer) {
            $topText = " Rekomendasi karyawan berkinerja tertinggi periode ini dipimpin oleh **{$topPerformer['name']}** dari departemen {$topPerformer['department']}.";
        }

        return $intro . $attendanceText . $taskText . $approvalText . $topText;
    }
}
