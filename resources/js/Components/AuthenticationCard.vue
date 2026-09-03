<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-zinc-950 text-zinc-100 antialiased selection:bg-cyan-500 selection:text-zinc-950 px-4">
        <div>
            <slot name="logo" />
        </div>

        <div class="w-full sm:max-w-md mt-6 p-8 bg-zinc-900/90 border border-zinc-800 shadow-2xl shadow-cyan-950/30 rounded-2xl overflow-hidden backdrop-blur-md">
            <slot />
        </div>
    </div>
</template>
