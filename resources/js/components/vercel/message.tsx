import { Message as AIMessage, ToolInvocation } from 'ai';
import { StreamableValue, useStreamableValue } from 'ai/rsc';
import { motion } from 'framer-motion';
import { BotIcon, UserIcon } from './icons';
import { Markdown } from './markdown';
import { Tracker } from './tracker';
import { Weather } from './weather';

export const TextStreamMessage = ({ content }: { content: StreamableValue }) => {
    const [text] = useStreamableValue(content);

    return (
        <motion.div
            className={`flex w-full flex-row gap-4 px-4 first-of-type:pt-20 md:w-[500px] md:px-0`}
            initial={{ y: 5, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
        >
            <div className="flex size-[24px] flex-shrink-0 flex-col items-center justify-center text-zinc-400">
                <BotIcon />
            </div>

            <div className="flex w-full flex-col gap-1">
                <div className="flex flex-col gap-4 text-zinc-800 dark:text-zinc-300">
                    <Markdown>{text}</Markdown>
                </div>
            </div>
        </motion.div>
    );
};

interface MessageProps {
    role: string;
    message: AIMessage & {
        parts?: Array<{
            type: string;
            toolInvocation?: ToolInvocation;
        }>;
    };
}

export const Message = ({ role, message }: MessageProps) => {
    const toolInvocations = message.parts?.filter((part) => part.type === 'tool-invocation').map((part) => part.toolInvocation);

    return (
        <motion.div
            className={`flex w-full flex-row gap-4 px-4 first-of-type:pt-20 md:w-[500px] md:px-0`}
            initial={{ y: 5, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
        >
            <div className="flex size-[24px] flex-shrink-0 flex-col items-center justify-center text-zinc-400">
                {role === 'assistant' ? <BotIcon /> : <UserIcon />}
            </div>

            <div className="flex w-full flex-col gap-6">
                {message.content && (
                    <div className="flex flex-col gap-4 text-zinc-800 dark:text-zinc-300">
                        <Markdown>{message.content as string}</Markdown>
                    </div>
                )}

                {toolInvocations && (
                    <div className="flex flex-col gap-4">
                        {toolInvocations.map((toolInvocation) => {
                            const { toolName, toolCallId, state } = toolInvocation;

                            if (state === 'result') {
                                const { result } = toolInvocation;

                                return (
                                    <div key={toolCallId}>
                                        {toolName === 'getWeather' ? (
                                            <Weather weatherAtLocation={result} />
                                        ) : toolName === 'viewTrackingInformation' ? (
                                            <div key={toolCallId}>
                                                <Tracker trackingInformation={result} />
                                            </div>
                                        ) : null}
                                    </div>
                                );
                            }
                        })}
                    </div>
                )}
            </div>
        </motion.div>
    );
};

export const ThinkingMessage = () => {
    return (
        <motion.div
            className={`flex w-full flex-row gap-4 px-4 first-of-type:pt-20 md:w-[500px] md:px-0`}
            initial={{ y: 5, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
        >
            <div className="flex size-[24px] flex-shrink-0 flex-col items-center justify-center text-zinc-400">
                <BotIcon />
            </div>

            <div className="flex w-full flex-col gap-6">
                <div className="flex flex-col gap-4 text-zinc-800 dark:text-zinc-300">
                    <Markdown>Thinking...</Markdown>
                </div>
            </div>
        </motion.div>
    );
};
