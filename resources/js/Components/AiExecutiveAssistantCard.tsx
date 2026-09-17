import React, { useState } from 'react';
import axios from 'axios';

interface AiExecutiveAssistantCardProps {
    initialData?: any;
}

export default function AiExecutiveAssistantCard({ initialData }: AiExecutiveAssistantCardProps) {
    const [summaryData, setSummaryData] = useState<any>(initialData || null);
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [conversation, setConversation] = useState<Array<{ type: 'user' | 'ai'; text: string; data?: any }>>([]);
    const [activeTab, setActiveTab] = useState<'summary' | 'top_performer' | 'chat'>('summary');

    const handleAsk = async (customQuery?: string) => {
        const textToAsk = customQuery || query;
        if (!textToAsk.trim()) return;

        setLoading(true);
        const userMsg = { type: 'user' as const, text: textToAsk };
        setConversation((prev) => [...prev, userMsg]);
        if (!customQuery) setQuery('');
        setActiveTab('chat');

        try {
            const res = await axios.post(route('ai-executive.ask'), { query: textToAsk });
            const aiMsg = { type: 'ai' as const, text: res.data.response, data: res.data.data };
            setConversation((prev) => [...prev, aiMsg]);
        } catch (error) {
            setConversation((prev) => [
                ...prev,
                { type: 'ai', text: 'Maaf, terjadi kesalahan saat menghubungkan ke AI Executive Assistant.' }
            ]);
        } finally {
            setLoading(false);
        }
    };

    const health = summaryData || {
        healthScore: 92,
        healthStatus: 'Excellent',
        healthColor: 'emerald',
        attendanceRate: 96,
        completedTasksMonth: 42,
        overdueTasksCount: 0,
        briefingNarrative: 'Operasional Swiss-Belinn Pekanbaru berjalan stabil dan efisien dengan indeks kesehatan 92/100.',
    };

    const top = summaryData?.topPerformer;

    return (
        <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-indigo-950 to-purple-950 text-white shadow-xl border border-indigo-500/20">
            {/* Background Decorative Glow */}
            <div className="absolute -top-24 -right-24 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl pointer-events-none" />
            <div className="absolute -bottom-24 -left-24 w-96 h-96 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none" />

            {/* Header Bar */}
            <div className="relative p-6 border-b border-white/10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <div className="h-11 w-11 rounded-xl bg-gradient-to-tr from-amber-400 via-purple-500 to-indigo-500 p-0.5 shadow-lg shadow-purple-500/20">
                        <div className="h-full w-full bg-slate-900 rounded-[10px] flex items-center justify-center">
                            <svg className="w-6 h-6 text-amber-300 animate-pulse" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div className="flex items-center gap-2">
                            <h3 className="text-xl font-bold tracking-tight bg-gradient-to-r from-white via-indigo-100 to-purple-200 bg-clip-text text-transparent">
                                AI Executive Assistant
                            </h3>
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30">
                                PRO 2026
                            </span>
                        </div>
                        <p className="text-xs text-indigo-200/70 mt-0.5">
                            Analisis Kesehatan Operasional & Rekomendasi Manajemen Real-time
                        </p>
                    </div>
                </div>

                {/* Health Index Meter Badge */}
                <div className="flex items-center gap-3 bg-white/5 backdrop-blur-md px-4 py-2.5 rounded-xl border border-white/10">
                    <div className="text-right">
                        <div className="text-[10px] font-semibold text-indigo-200 uppercase tracking-wider">Health Index</div>
                        <div className="text-sm font-extrabold text-emerald-400">
                            {health.healthStatus} ({health.healthScore}/100)
                        </div>
                    </div>
                    <div className="relative flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 font-bold text-sm">
                        {health.healthScore}
                    </div>
                </div>
            </div>

            {/* Navigation Tabs */}
            <div className="px-6 pt-3 flex items-center gap-2 border-b border-white/5 bg-black/10">
                <button
                    onClick={() => setActiveTab('summary')}
                    className={`px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors flex items-center gap-2 ${
                        activeTab === 'summary'
                            ? 'bg-white/10 text-white border-b-2 border-amber-400'
                            : 'text-indigo-200/60 hover:text-white hover:bg-white/5'
                    }`}
                >
                    <svg className="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Ringkasan Eksekutif
                </button>
                <button
                    onClick={() => setActiveTab('top_performer')}
                    className={`px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors flex items-center gap-2 ${
                        activeTab === 'top_performer'
                            ? 'bg-white/10 text-white border-b-2 border-amber-400'
                            : 'text-indigo-200/60 hover:text-white hover:bg-white/5'
                    }`}
                >
                    <svg className="w-4 h-4 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    Employee of the Month
                </button>
                <button
                    onClick={() => setActiveTab('chat')}
                    className={`px-4 py-2 text-xs font-semibold rounded-t-lg transition-colors flex items-center gap-2 ${
                        activeTab === 'chat'
                            ? 'bg-white/10 text-white border-b-2 border-amber-400'
                            : 'text-indigo-200/60 hover:text-white hover:bg-white/5'
                    }`}
                >
                    <svg className="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    Tanya AI ({conversation.length})
                </button>
            </div>

            {/* Tab Content Body */}
            <div className="p-6">
                {activeTab === 'summary' && (
                    <div className="space-y-5">
                        {/* AI Narrative Briefing Box */}
                        <div className="p-4 rounded-xl bg-white/5 border border-white/10 leading-relaxed text-sm text-indigo-100">
                            <div className="flex items-center gap-2 font-bold text-amber-300 mb-2 text-xs uppercase tracking-wider">
                                <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Executive Briefing
                            </div>
                            <p dangerouslySetInnerHTML={{
                                __html: (health.briefingNarrative || '').replace(/\*\*(.*?)\*\*/g, '<strong class="text-white font-semibold">$1</strong>')
                            }} />
                        </div>

                        {/* Quick Metrics Bar */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div className="p-3 rounded-xl bg-indigo-900/40 border border-indigo-500/20">
                                <div className="text-[10px] text-indigo-200/70 font-semibold uppercase">Kehadiran Bulan Ini</div>
                                <div className="text-xl font-bold text-white mt-1">{health.attendanceRate}%</div>
                            </div>
                            <div className="p-3 rounded-xl bg-emerald-900/40 border border-emerald-500/20">
                                <div className="text-[10px] text-emerald-200/70 font-semibold uppercase">Task Selesai</div>
                                <div className="text-xl font-bold text-emerald-300 mt-1">{health.completedTasksMonth}</div>
                            </div>
                            <div className="p-3 rounded-xl bg-rose-900/40 border border-rose-500/20">
                                <div className="text-[10px] text-rose-200/70 font-semibold uppercase">Overdue Tasks</div>
                                <div className="text-xl font-bold text-rose-300 mt-1">{health.overdueTasksCount}</div>
                            </div>
                            <div className="p-3 rounded-xl bg-amber-900/40 border border-amber-500/20">
                                <div className="text-[10px] text-amber-200/70 font-semibold uppercase">Pending Approvals</div>
                                <div className="text-xl font-bold text-amber-300 mt-1">{health.pendingApprovals}</div>
                            </div>
                        </div>

                        {/* Risk Alerts */}
                        {summaryData?.alerts && summaryData.alerts.length > 0 && (
                            <div className="space-y-2">
                                <div className="text-xs font-bold text-indigo-200/80 uppercase tracking-wider">Alerts & Warning Sistem</div>
                                {summaryData.alerts.map((alert: any, idx: number) => (
                                    <div key={idx} className="flex items-start gap-3 p-3 rounded-xl bg-black/20 border border-white/5">
                                        <span className="text-lg">
                                            {alert.type === 'danger' ? '🚨' : alert.type === 'warning' ? '⚠️' : '✅'}
                                        </span>
                                        <div>
                                            <h5 className="text-xs font-bold text-white">{alert.title}</h5>
                                            <p className="text-xs text-indigo-200/80 mt-0.5">{alert.message}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {activeTab === 'top_performer' && (
                    <div className="space-y-4">
                        {top ? (
                            <div className="relative overflow-hidden rounded-xl bg-gradient-to-r from-amber-500/10 via-purple-500/10 to-indigo-500/10 border border-amber-500/30 p-5">
                                <div className="flex flex-col sm:flex-row items-center gap-5">
                                    <div className="relative flex-shrink-0">
                                        <div className="w-20 h-20 rounded-2xl bg-gradient-to-tr from-amber-400 to-purple-600 p-1 shadow-lg shadow-amber-500/20">
                                            <div className="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center overflow-hidden">
                                                {top.photo ? (
                                                    <img src={`/storage/${top.photo}`} alt={top.name} className="w-full h-full object-cover" />
                                                ) : (
                                                    <span className="text-2xl font-bold text-amber-400">{top.name.charAt(0)}</span>
                                                )}
                                            </div>
                                        </div>
                                        <span className="absolute -bottom-2 -right-2 bg-amber-400 text-slate-950 font-black text-[10px] px-2 py-0.5 rounded-full shadow">
                                            #1 TOP
                                        </span>
                                    </div>
                                    <div className="min-w-0 flex-1 text-center sm:text-left">
                                        <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-400/20 text-amber-300 border border-amber-400/30 mb-1">
                                            🏆 Recommended Employee of the Month
                                        </div>
                                        <h4 className="text-lg font-bold text-white">{top.name}</h4>
                                        <p className="text-xs text-indigo-200">{top.position} — {top.department}</p>

                                        <div className="mt-3 grid grid-cols-3 gap-2 text-center">
                                            <div className="p-2 rounded-lg bg-white/5 border border-white/10">
                                                <div className="text-[10px] text-indigo-200/70">Composite Score</div>
                                                <div className="text-sm font-bold text-amber-300">{top.compositeScore}/100</div>
                                            </div>
                                            <div className="p-2 rounded-lg bg-white/5 border border-white/10">
                                                <div className="text-[10px] text-indigo-200/70">Rating Supervisor</div>
                                                <div className="text-sm font-bold text-emerald-300">{top.supervisorRating}/100</div>
                                            </div>
                                            <div className="p-2 rounded-lg bg-white/5 border border-white/10">
                                                <div className="text-[10px] text-indigo-200/70">Tugas Selesai</div>
                                                <div className="text-sm font-bold text-purple-300">{top.completedTasks} task</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm text-indigo-200/60 text-center py-6">Data karyawan belum cukup untuk rekomendasi.</p>
                        )}
                    </div>
                )}

                {activeTab === 'chat' && (
                    <div className="space-y-4">
                        <div className="max-h-64 overflow-y-auto space-y-3 pr-2 scrollbar-thin">
                            {conversation.length === 0 ? (
                                <div className="text-center py-8 text-indigo-200/60 text-xs">
                                    Pilih prompt cepat di bawah atau tulis pertanyaan mengenai operasional hotel.
                                </div>
                            ) : (
                                conversation.map((msg, idx) => (
                                    <div
                                        key={idx}
                                        className={`flex ${msg.type === 'user' ? 'justify-end' : 'justify-start'}`}
                                    >
                                        <div
                                            className={`max-w-[85%] rounded-2xl p-3.5 text-xs leading-relaxed ${
                                                msg.type === 'user'
                                                    ? 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-br-none'
                                                    : 'bg-white/10 border border-white/10 text-indigo-100 rounded-bl-none'
                                            }`}
                                            dangerouslySetInnerHTML={{
                                                __html: (msg.text || '').replace(/\*\*(.*?)\*\*/g, '<strong class="text-white font-bold">$1</strong>').replace(/\n/g, '<br/>')
                                            }}
                                        />
                                    </div>
                                ))
                            )}
                            {loading && (
                                <div className="flex justify-start">
                                    <div className="bg-white/10 rounded-2xl p-3 text-xs text-indigo-200 animate-pulse flex items-center gap-2">
                                        <svg className="w-4 h-4 animate-spin text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Menganalisis data operasional hotel...
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Quick Prompts Bar & Input Box */}
                <div className="mt-5 pt-4 border-t border-white/10 space-y-3">
                    <div className="flex flex-wrap gap-2">
                        <span className="text-[10px] font-bold text-indigo-300/60 uppercase tracking-wider self-center mr-1">Prompts Cepat:</span>
                        <button
                            onClick={() => handleAsk('Karyawan terbaik bulan ini')}
                            className="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/15 border border-white/10 text-[11px] text-amber-300 font-medium transition-colors"
                        >
                            ⭐ Karyawan Terbaik
                        </button>
                        <button
                            onClick={() => handleAsk('Analisis performa departemen')}
                            className="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/15 border border-white/10 text-[11px] text-indigo-200 font-medium transition-colors"
                        >
                            📊 Analisis Departemen
                        </button>
                        <button
                            onClick={() => handleAsk('Peringatan resiko operasional')}
                            className="px-2.5 py-1 rounded-lg bg-white/5 hover:bg-white/15 border border-white/10 text-[11px] text-rose-300 font-medium transition-colors"
                        >
                            🚨 Peringatan Risiko
                        </button>
                    </div>

                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            handleAsk();
                        }}
                        className="flex items-center gap-2"
                    >
                        <input
                            type="text"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Tanyakan ke AI Assistant (contoh: Status absensi minggu ini)..."
                            className="flex-1 bg-black/30 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white placeholder-indigo-300/40 focus:outline-none focus:border-amber-400/60 focus:ring-1 focus:ring-amber-400/60"
                        />
                        <button
                            type="submit"
                            disabled={loading || !query.trim()}
                            className="px-4 py-2.5 rounded-xl bg-gradient-to-r from-amber-400 via-amber-500 to-orange-500 hover:from-amber-300 hover:to-orange-400 text-slate-950 font-bold text-xs shadow-md shadow-amber-500/20 disabled:opacity-50 transition-all flex items-center gap-1.5"
                        >
                            <span>Kirim</span>
                            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    );
}
