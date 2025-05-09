const path = require('path');
const {defineConfig} = require('vite');

module.exports = defineConfig({
    publicDir: path.resolve(__dirname, 'public'),
    build: {
        copyPublicDir: false,
        outDir: path.resolve(__dirname, 'public/dist'),
        assetsDir: '',
        rollupOptions: {
            input: path.resolve(__dirname, 'resources/src/main.js'),
            output: {
                entryFileNames: `[name].js`,
                assetFileNames: `[name].[ext]`
            }
        }
    }
})
