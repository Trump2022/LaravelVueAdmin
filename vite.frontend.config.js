import { isAbsolute, relative, resolve, sep } from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), 'FRONTEND_');
    const outDir = (env.FRONTEND_OUT_DIR ?? '').trim();

    if (!outDir) {
        throw new Error('FRONTEND_OUT_DIR is empty. Set it to a relative SPA output directory before running build:frontend.');
    }

    const projectRoot = resolve(process.cwd());
    const resolvedOutDir = resolve(projectRoot, outDir);
    const relativeOutDir = relative(projectRoot, resolvedOutDir);

    if (
        isAbsolute(outDir)
        || relativeOutDir === ''
        || relativeOutDir === '..'
        || relativeOutDir.startsWith(`..${sep}`)
    ) {
        throw new Error('FRONTEND_OUT_DIR must be a directory inside the project root.');
    }

    return {
        plugins: [vue(), tailwindcss()],
        base: '/',
        publicDir: false,
        build: {
            outDir,
            emptyOutDir: true,
        },
    };
});


