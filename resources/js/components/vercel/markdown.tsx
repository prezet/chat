import React from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

const NonMemoizedMarkdown = ({ children }: { children: string }) => {
    const components = {
        code: ({ node, inline, className, children, ...props }: any) => {
            const match = /language-(\w+)/.exec(className || '');
            return !inline && match ? (
                <pre
                    {...props}
                    className={`${className} mt-2 w-[80dvw] overflow-x-scroll rounded bg-zinc-100 p-2 text-sm md:max-w-[500px] dark:bg-zinc-800`}
                >
                    <code className={match[1]}>{children}</code>
                </pre>
            ) : (
                <code className={`${className} rounded bg-zinc-100 px-1 py-0.5 text-sm dark:bg-zinc-800`} {...props}>
                    {children}
                </code>
            );
        },
        ol: ({ node, children, ...props }: any) => {
            return (
                <ol className="ml-4 list-inside list-decimal" {...props}>
                    {children}
                </ol>
            );
        },
        li: ({ node, children, ...props }: any) => {
            return (
                <li className="py-1" {...props}>
                    {children}
                </li>
            );
        },
        ul: ({ node, children, ...props }: any) => {
            return (
                <ul className="ml-4 list-inside list-decimal" {...props}>
                    {children}
                </ul>
            );
        },
        strong: ({ node, children, ...props }: any) => {
            return (
                <span className="font-semibold" {...props}>
                    {children}
                </span>
            );
        },
    };

    return (
        <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
            {children}
        </ReactMarkdown>
    );
};

export const Markdown = React.memo(NonMemoizedMarkdown, (prevProps, nextProps) => prevProps.children === nextProps.children);
