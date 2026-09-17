import React, { useState, useEffect } from 'react';
import axios from 'axios';

interface AiExecutiveAssistantCardProps {
    initialData?: any;
}

export default function AiExecutiveAssistantCard({ initialData }: AiExecutiveAssistantCardProps) {
    const [query, setQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [conversation, setConversation] = useState<Array<{ type: 'user' | 'ai'; text: string }>>([]);

    useEffect(() => {
        setConversation([{
            type: 'ai',
            text: "Halo, saya adalah AI Assistant HRMS. Anda bisa menanyakan tentang jumlah karyawan, status operasional, cuti hari ini, atau informasi lainnya."
        }]);
    }, []);

    const handleAsk = async (customQuery?: string) => {
        const textToAsk = customQuery || query;
        if (!textToAsk.trim()) return;

        setLoading(true);
        const userMsg = { type: 'user' as const, text: textToAsk };
        setConversation((prev) => [...prev, userMsg]);
        if (!customQuery) setQuery('');

        try {
            const res = await axios.post(route('ai-executive.ask'), { query: textToAsk });
            const aiMsg = { type: 'ai' as const, text: res.data.response };
            setConversation((prev) => [...prev, aiMsg]);
        } catch (error) {
            setConversation((prev) => [
                ...prev,
                { type: 'ai', text: 'Maaf, terjadi kesalahan saat menghubungkan ke AI Assistant.' }
            ]);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="bg-white border border-gray-200 rounded-lg shadow-sm flex flex-col h-[600px]">
            <div className="p-4 border-b border-gray-200 bg-gray-50 rounded-t-lg">
                <h3 className="font-semibold text-gray-800">Tanya AI Assistant</h3>
                <p className="text-xs text-gray-500">Asisten Pintar Operasional Hotel</p>
            </div>
            
            <div className="flex-1 p-4 overflow-y-auto space-y-4 bg-white">
                {conversation.map((msg, idx) => (
                    <div key={idx} className={`flex ${msg.type === 'user' ? 'justify-end' : 'justify-start'}`}>
                        <div
                            className={`max-w-[85%] rounded-lg px-4 py-3 text-sm ${
                                msg.type === 'user'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-800 border border-gray-200'
                            }`}
                            dangerouslySetInnerHTML={{
                                __html: (msg.text || '').replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br/>')
                            }}
                        />
                    </div>
                ))}
                {loading && (
                    <div className="flex justify-start">
                        <div className="bg-gray-100 text-gray-500 border border-gray-200 rounded-lg px-4 py-3 text-sm">
                            Mengetik...
                        </div>
                    </div>
                )}
            </div>

            <div className="p-4 border-t border-gray-200 bg-gray-50 rounded-b-lg">
                <div className="flex flex-wrap gap-2 mb-3">
                    <button onClick={() => handleAsk('Ringkasan operasional')} className="text-xs text-blue-600 hover:underline">Ringkasan Operasional</button>
                    <span className="text-gray-300">|</span>
                    <button onClick={() => handleAsk('Jumlah karyawan')} className="text-xs text-blue-600 hover:underline">Jumlah Karyawan</button>
                    <span className="text-gray-300">|</span>
                    <button onClick={() => handleAsk('Cuti hari ini')} className="text-xs text-blue-600 hover:underline">Cuti Hari Ini</button>
                </div>
                <form onSubmit={(e) => { e.preventDefault(); handleAsk(); }} className="flex gap-2">
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Ketik pertanyaan Anda..."
                        className="flex-1 bg-white border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                    />
                    <button
                        type="submit"
                        disabled={loading || !query.trim()}
                        className="px-4 py-2 bg-blue-600 text-white rounded-md text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                    >
                        Kirim
                    </button>
                </form>
            </div>
        </div>
    );
}
