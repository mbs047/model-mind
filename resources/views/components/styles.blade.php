<style>
    [x-cloak] {
        display: none !important;
    }

    .mbs-ai-chat-thinking-dot {
        animation: mbs-ai-chat-bounce 1s infinite;
    }

    .mbs-ai-chat-thinking-dot:nth-child(2) {
        animation-delay: 120ms;
    }

    .mbs-ai-chat-thinking-dot:nth-child(3) {
        animation-delay: 240ms;
    }

    @keyframes mbs-ai-chat-bounce {
        0%, 80%, 100% {
            transform: translateY(0);
            opacity: .45;
        }

        40% {
            transform: translateY(-.18rem);
            opacity: 1;
        }
    }
</style>
