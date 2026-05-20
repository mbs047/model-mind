<style>
    .model-mind-thinking-dot {
        animation: model-mind-bounce 1s infinite;
    }

    .model-mind-thinking-dot:nth-child(2) {
        animation-delay: 120ms;
    }

    .model-mind-thinking-dot:nth-child(3) {
        animation-delay: 240ms;
    }

    @keyframes model-mind-bounce {
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
