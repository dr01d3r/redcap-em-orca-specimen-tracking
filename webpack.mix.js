let mix = require('laravel-mix');

mix
    .sass('src/pages/shipment/style.scss', 'dist/pages/shipment')
    .js('src/pages/shipment/app.js', 'dist/pages/shipment')
    .sass('src/pages/dashboard/style.scss', 'dist/pages/dashboard')
    .js('src/pages/dashboard/app.js', 'dist/pages/dashboard')
    .sass('src/pages/report/style.scss', 'dist/pages/report')
    .js('src/pages/report/app.js', 'dist/pages/report')
    .vue();