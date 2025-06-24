import * as esbuild from 'esbuild'
import browserSync from 'browser-sync'
import dotenv from 'dotenv'
import fs from 'fs'
import path from 'path'


dotenv.config()

const __dirname = import.meta.dirname;
const isDev = process.argv.includes('--dev')
const hotFilePath = path.resolve(__dirname+'/../dist/.hot')

const context = await esbuild.context({
    define: {
        'process.env.NODE_ENV': isDev ? `'development'` : `'production'`,
    },
    bundle: true,
    treeShaking: true,
    minify: !isDev,
    sourcemap: isDev ? 'inline' : false,
    sourcesContent: isDev,
    target: ['es2020'],
    entryPoints: [
        './resources/js/components/chat-widget.js',
    ],
    outdir: './dist',
})

if (! isDev) {
    await context.rebuild()
    await context.dispose()
} else {
    await context.watch();

    fs.writeFileSync(hotFilePath, '')

    const cleanup = () => {
        if (fs.existsSync(hotFilePath)) {
            fs.unlinkSync(hotFilePath)
        }

        process.exit(0)
    }

    process.on('SIGINT', () => cleanup())
    process.on('SIGTERM', () => cleanup())

    browserSync.init({
        proxy: process.env.APP_URL || "localhost",
        files: [
            "resources/css/*.css",
            "resources/js/**/*.js",
            "resources/views/**/*.blade.php",
        ],
        ui: false,
        open: false,
        port: process.env.BROWSERSYNC_PORT || 3000
    })
}
