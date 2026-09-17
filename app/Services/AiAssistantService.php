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

        // Check if Gemini API is configured
        $apiKey = config('services.gemini.key');
        
        if (!empty($apiKey)) {
            try {
                $prompt = "Anda adalah AI Executive Assistant untuk sistem HRMS hotel Swiss-Belinn Pekanbaru.\n"
                        . "Tugas Anda: Jawab pertanyaan pengguna secara akurat, singkat, dan profesional dalam bahasa Indonesia berdasarkan Data JSON berikut.\n\n"
                        . "DATA OPERASIONAL (JSON):\n" . json_encode($summary) . "\n\n"
                        . "PERTANYAAN PENGGUNA:\n" . $query;

                $response = \Illuminate\Support\Facades\Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                    'contents' => [
                        [
                            'parts' => [['text' => $prompt]]
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $result = $response->json();
                    $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    
                    if ($generatedText) {
                        return [
                            'query' => $query,
                            'category' => 'gemini_generative',
                            'response' => trim($generatedText),
                            'data' => null, // Omit detailed data array when generative handles it
                        ];
                    }
                }
            } catch (\Exception $e) {
                // Silently fallback to local keyword matching
            }
        }

        // --- Local Keyword Matching Fallback ---

        // Topic: Employees Count
        if ($this->matchIntent($queryLower, [['karyawan', 'pegawai', 'staf', 'staff', 'pekerja', 'orang'], ['jumlah', 'total', 'berapa', 'banyak', 'hitung']])) {
            $totalEmployees = \App\Models\User::count();
            return [
                'query' => $query,
                'category' => 'general_info',
                'response' => "**Informasi Karyawan:**\n\nSaat ini terdapat **{$totalEmployees} karyawan** yang terdaftar dalam sistem HRMS.",
                'data' => ['total_employees' => $totalEmployees],
            ];
        }

        // Topic: Projects
        if ($this->matchIntent($queryLower, [['project', 'proyek'], ['jumlah', 'total', 'berapa', 'banyak', 'aktif']])) {
            $totalProjects = \App\Models\Project::count();
            $activeProjects = \App\Models\Project::where('status', '!=', 'completed')->count();
            return [
                'query' => $query,
                'category' => 'general_info',
                'response' => "**Informasi Project:**\n\nSaat ini terdapat total **{$totalProjects} project**, dengan **{$activeProjects} project** yang masih berstatus aktif (sedang berjalan).",
                'data' => ['total_projects' => $totalProjects, 'active_projects' => $activeProjects],
            ];
        }

        // Topic: Leave/Absensi
        if ($this->matchIntent($queryLower, [['cuti', 'libur', 'absen', 'tidak masuk'], ['siapa', 'berapa', 'hari ini', 'sedang', 'info', 'data']])) {
            $leavesToday = \App\Models\LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->with('user')
                ->get();
            
            if ($leavesToday->isEmpty()) {
                $response = "**Informasi Cuti:**\n\nTidak ada karyawan yang sedang mengambil cuti hari ini.";
            } else {
                $names = $leavesToday->pluck('user.name')->implode(', ');
                $response = "**Informasi Cuti:**\n\nHari ini ada **{$leavesToday->count()} karyawan** yang sedang cuti, yaitu: {$names}.";
            }
            return [
                'query' => $query,
                'category' => 'general_info',
                'response' => $response,
                'data' => ['leaves_today_count' => $leavesToday->count()],
            ];
        }

        // Topic: Departments Count
        if ($this->matchIntent($queryLower, [['departemen', 'divisi', 'bagian'], ['jumlah', 'total', 'berapa', 'banyak']])) {
            $totalDepts = \App\Models\Department::count();
            return [
                'query' => $query,
                'category' => 'general_info',
                'response' => "**Informasi Departemen:**\n\nSaat ini terdapat **{$totalDepts} departemen** yang terstruktur dalam sistem HRMS hotel.",
                'data' => ['total_departments' => $totalDepts],
            ];
        }

        // Topic: Overtime/Lembur
        if ($this->matchIntent($queryLower, [['lembur', 'overtime']])) {
            $overtimePending = \App\Models\OvertimeRequest::where('status', 'pending')->count();
            $overtimeApprovedToday = \App\Models\OvertimeRequest::where('status', 'approved')
                ->whereDate('date', now())
                ->with('user')
                ->get();
            
            $response = "**Informasi Lembur:**\n\n";
            $response .= "Terdapat **{$overtimePending} pengajuan lembur** yang masih menunggu persetujuan (pending).\n";
            
            if ($overtimeApprovedToday->isEmpty()) {
                $response .= "Untuk hari ini, belum ada karyawan yang dijadwalkan lembur (approved).";
            } else {
                $names = $overtimeApprovedToday->pluck('user.name')->implode(', ');
                $response .= "Hari ini ada **{$overtimeApprovedToday->count()} karyawan** yang disetujui untuk lembur, yaitu: {$names}.";
            }

            return [
                'query' => $query,
                'category' => 'general_info',
                'response' => $response,
                'data' => [
                    'pending_overtime' => $overtimePending,
                    'approved_today' => $overtimeApprovedToday->count()
                ],
            ];
        }

        // Topic: Announcements
        if ($this->matchIntent($queryLower, [['pengumuman', 'berita', 'informasi', 'update', 'kabar'], ['terbaru', 'hari ini', 'terkini', 'apa']])) {
            $latestAnnouncement = \App\Models\Announcement::latest()->first();
            
            if ($latestAnnouncement) {
                $date = $latestAnnouncement->created_at->translatedFormat('d F Y');
                $response = "**Pengumuman Terbaru ({$date}):**\n\n" .
                    "**{$latestAnnouncement->title}**\n" .
                    $latestAnnouncement->content;
            } else {
                $response = "**Informasi Pengumuman:**\n\nBelum ada pengumuman terbaru yang dipublikasikan di sistem.";
            }

            return [
                'query' => $query,
                'category' => 'general_info',
                'response' => $response,
                'data' => ['latest_announcement' => $latestAnnouncement],
            ];
        }

        // Topic 1: Employee of the Month / Top Performers
        if ($this->matchIntent($queryLower, [['terbaik', 'bagus', 'rajin', 'performa', 'kinerja', 'prestasi', 'employee of the month']])) {
            $top = $summary['topPerformer'];
            if ($top) {
                $response = "**Rekomendasi Karyawan Terbaik (Employee of the Month):**\n\n" .
                    "Karyawan terbaik yang direkomendasikan sistem adalah **{$top['name']}** ({$top['position']} - {$top['department']}) dengan Skor Performa **{$top['compositeScore']}/100**.\n\n" .
                    "**Highlights Pencapaian:**\n" .
                    "• Menyelesaikan **{$top['completedTasks']} tugas** dengan sukses.\n" .
                    "• Skor Penilaian Supervisor: **{$top['supervisorRating']}/100**.\n" .
                    "• Kedisiplinan Kehadiran: **{$top['attendanceRate']}%**.\n\n" .
                    "*Saran Manajemen*: Berikan apresiasi atau sertifikat 'Employee of the Month' pada briefing bulanan berikutnya.";
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
        if ($this->matchIntent($queryLower, [['departemen', 'divisi', 'bagian'], ['analisis', 'kinerja', 'performa', 'status', 'kesehatan']])) {
            $depts = $summary['departmentBreakdown'];
            $deptList = '';
            foreach ($depts as $d) {
                $deptList .= "• **{$d['name']}**: {$d['completed_tasks']} Selesai, {$d['active_tasks']} Aktif (Tingkat Penyelesaian: {$d['completion_rate']}%)\n";
            }

            $response = "**Analisis Kesehatan Operasional per Departemen:**\n\n" .
                $deptList . "\n" .
                "*Rekomendasi*: Fokuskan alokasi sumber daya ke departemen dengan beban *active tasks* yang masih tinggi.";

            return [
                'query' => $query,
                'category' => 'department_analysis',
                'response' => $response,
                'data' => $depts,
            ];
        }

        // Topic 3: Risk Alerts / Overdue Tasks
        if ($this->matchIntent($queryLower, [['resiko', 'risiko', 'terlambat', 'overdue', 'masalah', 'peringatan', 'bahaya', 'alert']])) {
            $alerts = $summary['alerts'];
            $alertText = '';
            foreach ($alerts as $a) {
                $alertText .= "• **{$a['title']}**: {$a['message']}\n";
            }

            $response = "**Peringatan Risiko Operasional & Task Delay:**\n\n" .
                "Saat ini terdapat **{$summary['overdueTasksCount']} tugas overdue** dan **{$summary['pendingApprovals']} pengajuan menunggu approval**.\n\n" .
                "**Rincian Alert Sistem:**\n" . ($alertText ?: 'Tidak ada alert kritis saat ini.') . "\n" .
                "*Tindakan Disarankan*: Instruksikan Head of Department terkait untuk mempercepat *review* dan verifikasi tugas.";

            return [
                'query' => $query,
                'category' => 'risk_alerts',
                'response' => $response,
                'data' => $alerts,
            ];
        }

        // Catch-all Fallback for Unrecognized Questions
        if ($this->matchIntent($queryLower, [['siapa', 'apa', 'bagaimana', 'kapan', 'dimana', 'kenapa', 'mengapa', 'tolong']])) {
            return [
                'query' => $query,
                'category' => 'unrecognized',
                'response' => "Maaf, karena saya berjalan secara lokal tanpa API eksternal (LLM), pemahaman saya terbatas pada topik spesifik. Anda dapat menanyakan seputar:\n• Jumlah karyawan/staf/pekerja\n• Informasi cuti/absen hari ini\n• Data lembur hari ini\n• Rekomendasi karyawan terbaik/performa\n• Analisis kinerja departemen\n• Peringatan risiko operasional\n• Pengumuman/berita terbaru",
                'data' => null,
            ];
        }

        // Topic 4: General Executive Briefing (Default / Empty / Summary)
        $response = "**Rangkuman Eksekutif AI (Swiss-Belinn HRMS):**\n\n" .
            $summary['briefingNarrative'] . "\n\n" .
            "**Status Ringkas:**\n" .
            "• Indeks Kesehatan Operasional: **{$summary['healthScore']}/100 ({$summary['healthStatus']})**\n" .
            "• Tingkat Kehadiran Bulanan: **{$summary['attendanceRate']}%**\n" .
            "• Tugas Selesai Bulan Ini: **{$summary['completedTasksMonth']} tugas**\n" .
            "• Tugas Melebihi Deadline: **{$summary['overdueTasksCount']} tugas**\n\n" .
            "Silakan tanyakan detail spesifik seperti *Jumlah Karyawan*, *Performa Karyawan*, *Analisis Departemen*, atau *Alert Risiko*.";

        return [
            'query' => $query,
            'category' => 'executive_summary',
            'response' => $response,
            'data' => $summary,
        ];
    }

    /**
     * Engine for matching complex intents using grouped synonymous keywords.
     * It requires at least one keyword from each provided group to match the query.
     */
    private function matchIntent(string $query, array $keywordGroups): bool
    {
        foreach ($keywordGroups as $group) {
            $groupMatched = false;
            foreach ($group as $word) {
                if (str_contains($query, $word)) {
                    $groupMatched = true;
                    break;
                }
            }
            // If any group completely fails to match, the intent is not a match.
            if (!$groupMatched) {
                return false;
            }
        }
        return true;
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
