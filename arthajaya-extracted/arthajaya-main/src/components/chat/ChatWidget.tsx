import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Send, X, MessageSquare, Loader2 } from 'lucide-react';

interface Message {
  role: 'user' | 'assistant';
  content: string;
}

export default function ChatWidget() {
  const [isOpen, setIsOpen] = useState(false);
  const [showPopup, setShowPopup] = useState(true);
  const [messages, setMessages] = useState<Message[]>([
    { role: 'assistant', content: 'Halo! Saya FIN-J. Ada yang bisa saya bantu hari ini?' }
  ]);
  const [input, setInput] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  const handleSend = async () => {
    if (!input.trim()) return;

    const userMessage: Message = { role: 'user', content: input };
    setMessages((prev) => [...prev, userMessage]);
    setInput('');
    setIsLoading(true);

    try {
      const response = await fetch('https://api.groq.com/openai/v1/chat/completions', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${import.meta.env.VITE_GROQ_API_KEY}`
        },
        body: JSON.stringify({
          model: 'llama-3.1-8b-instant',
          messages: [
            {
              role: 'system',
              content: 'Anda adalah FIN-J, asisten AI virtual untuk Arthajaya (sebuah koperasi/lembaga keuangan). Karakter Anda ramah, profesional, solutif, dan menggunakan bahasa Indonesia yang santai tapi sopan. Jawablah dengan singkat dan padat. Fokus pada membantu pengguna memahami layanan Arthajaya seperti simpanan dan pinjaman.'
            },
            ...messages.map(msg => ({ role: msg.role, content: msg.content })),
            { role: 'user', content: input }
          ],
          temperature: 0.7,
          max_tokens: 500
        })
      });

      const data = await response.json();
      const assistantMessage: Message = {
        role: 'assistant',
        content: data.choices[0].message.content
      };
      setMessages((prev) => [...prev, assistantMessage]);
    } catch (error) {
      console.error('Error calling Groq API:', error);
      setMessages((prev) => [...prev, { role: 'assistant', content: 'Maaf, terjadi kesalahan. Silakan coba lagi nanti.' }]);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="fixed bottom-6 right-6 z-50 flex flex-col items-end">
      {/* Popup Message */}
      <AnimatePresence>
        {showPopup && !isOpen && (
          <motion.div
            initial={{ opacity: 0, y: 20, scale: 0.9 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, scale: 0.9 }}
            className="mb-4 bg-white dark:bg-slate-800 p-4 rounded-2xl shadow-xl border border-slate-100 dark:border-slate-700 max-w-xs relative"
          >
            <button
              onClick={() => setShowPopup(false)}
              className="absolute top-2 right-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
            >
              <X size={14} />
            </button>
            <div className="flex items-start gap-3">
              <img src="/FIN-J.svg" alt="FIN-J" className="w-10 h-10 rounded-full" />
              <div>
                <p className="text-xs font-semibold text-primary-600 dark:text-primary-400">FIN-J</p>
                <p className="text-sm text-slate-600 dark:text-slate-300">👋 Hai! Mau tanya apa hari ini?</p>
              </div>
            </div>
            <div className="absolute bottom-[-8px] right-6 w-4 h-4 bg-white dark:bg-slate-800 rotate-45 border-r border-b border-slate-100 dark:border-slate-700"></div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Chat Window */}
      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: 50, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 50, scale: 0.95 }}
            className="mb-4 w-[380px] h-[500px] bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-100 dark:border-slate-800 flex flex-col overflow-hidden"
          >
            {/* Header */}
            <div className="bg-gradient-to-r from-primary-600 to-indigo-600 p-4 text-white flex justify-between items-center">
              <div className="flex items-center gap-3">
                <div className="relative">
                  <img src="/FIN-J.svg" alt="FIN-J" className="w-10 h-10 rounded-full bg-white p-0.5" />
                  <span className="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
                </div>
                <div>
                  <h3 className="font-bold">FIN-J</h3>
                  <p className="text-xs text-primary-100">Asisten Virtual Arthajaya</p>
                </div>
              </div>
              <button
                onClick={() => setIsOpen(false)}
                className="text-white/80 hover:text-white transition-colors"
              >
                <X size={20} />
              </button>
            </div>

            {/* Messages */}
            <div className="flex-1 p-4 overflow-y-auto space-y-4 bg-slate-50 dark:bg-slate-950">
              {messages.map((msg, index) => (
                <div
                  key={index}
                  className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  <div
                    className={`max-w-[75%] p-3 rounded-2xl ${
                      msg.role === 'user'
                        ? 'bg-primary-600 text-white rounded-br-none'
                        : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 shadow-sm rounded-bl-none border border-slate-100 dark:border-slate-700'
                    }`}
                  >
                    <p className="text-sm">{msg.content}</p>
                  </div>
                </div>
              ))}
              {isLoading && (
                <div className="flex justify-start">
                  <div className="bg-white dark:bg-slate-800 p-3 rounded-2xl rounded-bl-none shadow-sm border border-slate-100 dark:border-slate-700">
                    <Loader2 size={16} className="animate-spin text-slate-400" />
                  </div>
                </div>
              )}
            </div>

            {/* Input */}
            <div className="p-4 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900">
              <div className="flex gap-2">
                <input
                  type="text"
                  value={input}
                  onChange={(e) => setInput(e.target.value)}
                  onKeyPress={(e) => e.key === 'Enter' && handleSend()}
                  placeholder="Ketik pertanyaan Anda..."
                  className="flex-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 p-2.5 rounded-xl focus:outline-none focus:ring-2 focus:ring-primary-500 text-sm"
                />
                <button
                  onClick={handleSend}
                  disabled={isLoading || !input.trim()}
                  className="bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white p-2.5 rounded-xl transition-colors"
                >
                  <Send size={18} />
                </button>
              </div>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Toggle Button */}
      <motion.button
        whileHover={{ scale: 1.05 }}
        whileTap={{ scale: 0.95 }}
        onClick={() => {
          setIsOpen(!isOpen);
          setShowPopup(false);
        }}
        className="w-14 h-14 bg-gradient-to-r from-primary-600 to-indigo-600 text-white rounded-full shadow-2xl flex items-center justify-center hover:shadow-primary-500/20 transition-shadow"
      >
        {isOpen ? <X size={24} /> : <MessageSquare size={24} />}
      </motion.button>
    </div>
  );
}
