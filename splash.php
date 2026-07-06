<div id="pitcrewSplash" class="pitcrew-splash" aria-label="PitCrew Connect welcome screen">
    <div class="splash-bg-glow splash-glow-left"></div>
    <div class="splash-bg-glow splash-glow-right"></div>

    <div class="splash-center">
        <div class="splash-brand-group">
            <div class="splash-logo-box">▦</div>

            <div class="splash-text-mask">
                <span class="splash-brand-text">PitCrew Connect</span>
            </div>
        </div>
    </div>
</div>

<style>
    body.pitcrew-splash-lock {
        overflow: hidden;
    }

    .pitcrew-splash {
        position: fixed;
        inset: 0;
        z-index: 9999;
        overflow: hidden;
        background:
            radial-gradient(circle at 75% 35%, rgba(249, 115, 22, 0.20), transparent 28%),
            radial-gradient(circle at 25% 80%, rgba(250, 204, 21, 0.10), transparent 24%),
            linear-gradient(135deg, #030816 0%, #06112b 45%, #1b120d 100%);
        transition: opacity 0.85s ease, visibility 0.85s ease;
    }

    .pitcrew-splash.is-hidden {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .splash-bg-glow {
        position: absolute;
        border-radius: 999px;
        filter: blur(10px);
        opacity: 0.5;
    }

    .pitcrew-splash.is-playing .splash-bg-glow {
        animation: splashGlowFloat 5s ease-in-out infinite alternate;
    }

    .splash-glow-left {
        width: 300px;
        height: 300px;
        left: -80px;
        bottom: -70px;
        background: rgba(250, 204, 21, 0.10);
    }

    .splash-glow-right {
        width: 320px;
        height: 320px;
        right: -80px;
        top: 8%;
        background: rgba(249, 115, 22, 0.14);
    }

    .pitcrew-splash.is-playing .splash-glow-right {
        animation-delay: 0.8s;
    }

    .splash-center {
        width: 100%;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .splash-brand-group {
        --logo-size: 52px;
        --gap-size: 24px;
        --logo-start-shift: 212px;

        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: var(--gap-size);
        position: relative;
    }

    .splash-logo-box {
        width: var(--logo-size);
        height: var(--logo-size);
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 15px;
        background: linear-gradient(135deg, #facc15, #f59e0b 55%, #f97316);
        color: #081120;
        font-size: 20px;
        font-weight: 900;
        box-shadow: 0 14px 30px rgba(249, 115, 22, 0.30);
        z-index: 3;

        /* Starting position: logo begins near the middle of the screen */
        transform: translateX(var(--logo-start-shift)) scale(0.96);
    }

    .pitcrew-splash.is-playing .splash-logo-box {
        animation: splashLogoMoveLeft 1.45s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: 0.18s;
    }

    .splash-text-mask {
        width: max-content;
        max-width: calc(100vw - 120px);
        height: 64px;
        display: flex;
        align-items: center;
        overflow: hidden;

        /* Starting state: text hidden, ready to come from behind the logo */
        clip-path: inset(0 100% 0 0);
    }

    .pitcrew-splash.is-playing .splash-text-mask {
        animation: splashTextWindowOpen 1.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: 0.48s;
    }

    .splash-brand-text {
        display: inline-block;
        white-space: nowrap;
        color: #ffffff;
        font-size: 46px;
        font-weight: 950;
        line-height: 1;
        letter-spacing: -0.055em;
        text-shadow: 0 8px 24px rgba(0, 0, 0, 0.24);

        /* Starting state: text sits behind the logo area */
        transform: translateX(calc(-1 * (var(--logo-size) + var(--gap-size))));
        opacity: 0;
    }

    .pitcrew-splash.is-playing .splash-brand-text {
        animation: splashTextMoveRight 1.5s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        animation-delay: 0.48s;
    }

    @keyframes splashLogoMoveLeft {
        0% {
            transform: translateX(var(--logo-start-shift)) scale(0.96);
        }

        100% {
            transform: translateX(0) scale(1);
        }
    }

    @keyframes splashTextWindowOpen {
        0% {
            clip-path: inset(0 100% 0 0);
        }

        100% {
            clip-path: inset(0 0 0 0);
        }
    }

    @keyframes splashTextMoveRight {
        0% {
            transform: translateX(calc(-1 * (var(--logo-size) + var(--gap-size))));
            opacity: 0;
        }

        20% {
            opacity: 1;
        }

        100% {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes splashGlowFloat {
        from {
            transform: translate3d(0, 0, 0) scale(1);
        }

        to {
            transform: translate3d(16px, -18px, 0) scale(1.05);
        }
    }

    @media (max-width: 760px) {
        .splash-brand-group {
            --logo-size: 46px;
            --gap-size: 20px;
            --logo-start-shift: 165px;
        }

        .splash-logo-box {
            border-radius: 13px;
            font-size: 18px;
        }

        .splash-text-mask {
            height: 56px;
        }

        .splash-brand-text {
            font-size: 36px;
        }
    }

    @media (max-width: 520px) {
        .splash-brand-group {
            --logo-size: 40px;
            --gap-size: 16px;
            --logo-start-shift: 120px;
        }

        .splash-logo-box {
            border-radius: 12px;
            font-size: 16px;
        }

        .splash-text-mask {
            height: 48px;
            max-width: calc(100vw - 86px);
        }

        .splash-brand-text {
            font-size: 28px;
            letter-spacing: -0.05em;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const splash = document.getElementById('pitcrewSplash');
        if (!splash) return;

        const splashDuration = 3500;

        function hideSplash() {
            splash.classList.add('is-hidden');
            document.body.classList.remove('pitcrew-splash-lock');

            setTimeout(function () {
                splash.remove();
            }, 900);
        }

        document.body.classList.add('pitcrew-splash-lock');

        /*
            Start animation only after the browser has painted the initial state.
            This makes the animation replay correctly even when opening the URL again.
        */
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                splash.classList.add('is-playing');
            });
        });

        setTimeout(hideSplash, splashDuration);
    });
</script>