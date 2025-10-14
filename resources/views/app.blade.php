<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite('resources/js/app.js')
    @inertiaHead
    @routes
    <style>
      /* Barra de progreso de Inertia */
      .inertia-progress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background-color: #3b82f6;
        z-index: 9999;
        transition: width 0.3s ease;
      }
    </style>
  </head>
  <body>
    @inertia
  </body>
</html>
