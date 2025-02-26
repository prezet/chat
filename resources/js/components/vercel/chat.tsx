import AppLogoIcon from '@/components/app-logo-icon';
import { VercelIcon } from '@/components/vercel/icons';
import { Message, ThinkingMessage } from '@/components/vercel/message';
import { useScrollToBottom } from '@/components/vercel/use-scroll-to-bottom';
import { generateUUID } from '@/lib/utils';
import { useChat } from '@ai-sdk/react';
import { usePage } from '@inertiajs/react';
import { Message as AIMessage } from 'ai';
import { motion } from 'framer-motion';
import { useRef, useState } from 'react';
import { toast } from 'sonner';

export const ChatPanel = () => {
    const pageProps = usePage().props as unknown as {
        _token: string;
        chatId: string;
        initialMessages?: AIMessage[];
        chatUrl: string;
    };

    const { _token, chatId, initialMessages, chatUrl } = pageProps;

    const [isResponding, setIsResponding] = useState(false);

    const { messages, handleSubmit, input, setInput, append } = useChat({
        id: chatId,
        initialMessages,
        // By default it POSTs with { "prompt": ... }, so let's override:
        api: chatUrl,
        body: {}, // We'll manually form the body in handleSubmit
        // If you want to set custom headers:
        sendExtraMessageFields: true,
        headers: {
            Accept: 'application/json',
            ContentType: 'application/json',
            'X-CSRF-TOKEN': _token,
        },
        generateId: generateUUID,
        onError: (error) => {
            console.error('Chat error:', error);
            setIsResponding(false);
            toast.error('Failed to get response', {
                description: error.message || 'Please try again',
            });
        },
        onFinish: (message, { finishReason }) => {
            console.log('Chat finished:', finishReason);
            setIsResponding(false);
        },
    });

    const inputRef = useRef<HTMLInputElement>(null);
    const [messagesContainerRef, messagesEndRef] = useScrollToBottom<HTMLDivElement>();

    const suggestedActions = [
        {
            title: "What's the weather",
            label: 'in San Francisco?',
            action: "What's the weather in San Francisco?",
        },
        {
            title: "What's the weather",
            label: 'in New York?',
            action: "What's the weather in New York?",
        },
    ];

    const handleFormSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
        toast.dismiss(); // Clear any existing error toasts
        setIsResponding(true);
        await handleSubmit(e);
    };

    const handleSuggestedAction = async (action: string) => {
        toast.dismiss(); // Clear any existing error toasts
        setIsResponding(true);
        await append({
            role: 'user',
            content: action,
        });
    };

    return (
        <>
            <div className="flex flex-col justify-between gap-4">
                <div ref={messagesContainerRef} className="flex h-full w-full flex-col items-center gap-6">
                    {messages.length === 0 && (
                        <motion.div className="h-[350px] w-full px-4 pt-20 md:w-[500px] md:px-0">
                            <div className="flex flex-col gap-4 rounded-lg border p-6 text-sm text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
                                <p className="flex flex-row items-center justify-center gap-4 text-zinc-900 dark:text-zinc-50">
                                    <AppLogoIcon className="size-5 fill-current text-zinc-900 dark:text-zinc-50" />
                                    <span>+</span>
                                    <VercelIcon size={20} />
                                </p>
                                <p>
                                    This demo uses Vercel's{' '}
                                    <a
                                        target="_blank"
                                        className="cursor-pointer font-semibold underline"
                                        href="https://sdk.vercel.ai/docs/ai-sdk-ui/overview"
                                    >
                                        AI SDK UI
                                    </a>{' '}
                                    to interact with large language models via a Laravel backend powered by{' '}
                                    <a target="_blank" className="cursor-pointer font-semibold underline" href="https://prism.echolabs.dev/">
                                        Prism
                                    </a>
                                    . Built on the Laravel{' '}
                                    <a
                                        target="_blank"
                                        className="cursor-pointer font-semibold underline"
                                        href="https://github.com/laravel/react-starter-kit"
                                    >
                                        React Starter Kit
                                    </a>{' '}
                                    with UI elements taken from Vercel's{' '}
                                    <a target="_blank" className="cursor-pointer font-semibold underline" href="https://vercel.com/templates/ai">
                                        AI Templates
                                    </a>
                                    .
                                </p>
                            </div>
                        </motion.div>
                    )}

                    {messages.map((message) => (
                        <Message key={message.id} role={message.role} message={message} />
                    ))}

                    {isResponding && messages.length > 0 && <ThinkingMessage />}

                    <div ref={messagesEndRef} />
                </div>

                <div className="mx-auto mb-4 grid w-full gap-2 px-4 sm:grid-cols-2 md:max-w-[500px] md:px-0">
                    {messages.length === 0 &&
                        suggestedActions.map((suggestedAction, index) => (
                            <motion.div
                                initial={{ opacity: 0, y: 20 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: 0.05 * index }}
                                key={index}
                                className={index > 1 ? 'hidden sm:block' : 'block'}
                            >
                                <button
                                    onClick={() => handleSuggestedAction(suggestedAction.action)}
                                    disabled={isResponding}
                                    className="flex w-full flex-col rounded-lg border border-zinc-200 p-2 text-left text-sm text-zinc-800 transition-colors hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                >
                                    <span className="font-medium">{suggestedAction.title}</span>
                                    <span className="text-zinc-500 dark:text-zinc-400">{suggestedAction.label}</span>
                                </button>
                            </motion.div>
                        ))}
                </div>

                <form className="relative flex flex-col items-center gap-2" onSubmit={handleFormSubmit}>
                    <input
                        ref={inputRef}
                        className="w-full max-w-[calc(100dvw-32px)] rounded-md bg-zinc-100 px-2 py-1.5 text-zinc-800 outline-none disabled:cursor-not-allowed disabled:opacity-50 md:max-w-[500px] dark:bg-zinc-700 dark:text-zinc-300"
                        placeholder="Send a message..."
                        value={input}
                        onChange={(event) => {
                            setInput(event.target.value);
                        }}
                        disabled={isResponding}
                    />
                </form>
            </div>
        </>
    );
};
