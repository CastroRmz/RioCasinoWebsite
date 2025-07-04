<?php http_response_code(404); ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🎰 404 - Página no encontrada | Casino Royale</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Font Awesome para íconos -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Trebuchet MS', sans-serif;
      background: radial-gradient(circle at center, #0f0f0f 0%, #1a1a1a 100%);
      color: #fff;
      text-align: center;
      height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      background-image: url('https://cdn.pixabay.com/photo/2017/08/10/06/52/poker-2618446_1280.jpg');
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      position: relative;
    }

    .overlay {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background-color: rgba(0, 0, 0, 0.75);
      z-index: 0;
    }

    .content {
      position: relative;
      z-index: 1;
      padding: 40px;
      backdrop-filter: blur(4px);
    }

    h1 {
      font-size: 110px;
      margin: 0;
      color: #FFD700;
      text-shadow: 0 0 15px #FF0000;
    }

    h1 i {
      font-size: 80px;
      color: #e74c3c;
      margin-right: 10px;
    }

    h2 {
      font-size: 26px;
      margin: 20px 0;
    }

    p {
      font-size: 18px;
      color: #ccc;
      margin-bottom: 30px;
    }

    a {
      padding: 12px 30px;
      font-size: 16px;
      background-color: #c0392b;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      transition: background-color 0.3s;
    }

    a:hover {
      background-color: #e74c3c;
    }

    .icons {
      font-size: 30px;
      margin-top: 30px;
      color: #f1c40f;
    }

    .icons i {
      margin: 0 10px;
      text-shadow: 0 0 5px #000;
    }

    .chips {
      margin-top: 30px;
    }

    .chip {
      display: inline-block;
      width: 60px;
      height: 60px;
      margin: 5px;
      border-radius: 50%;
      background-color: #e67e22;
      box-shadow: 0 0 10px #fff;
      line-height: 60px;
      font-weight: bold;
      color: white;
      text-shadow: 0 0 5px #000;
    }
  </style>
</head>
<body>
  <div class="overlay"></div>
  <div class="content">
    <h1><i class="fas fa-dice"></i>404</h1>
    <h2><i class="fas fa-times-circle"></i> ¡Página no encontrada!</h2>
    <p>Oops... Esta jugada no te favoreció. Vuelve al lobby del casino y prueba suerte de nuevo.</p>
    <a href="/"><i class="fas fa-home"></i> Volver al casino</a>

    <div class="chips">
      <div class="chip">10</div>
      <div class="chip">50</div>
      <div class="chip">100</div>
      <div class="chip"><i class="fas fa-star"></i></div>
    </div>

    <div class="icons">
      <i class="fas fa-dice"></i>
      <i class="fas fa-coins"></i>
      <i class="fas fa-heart"></i>
      <i class="fas fa-chess-king"></i>
      <i class="fas fa-spade"></i>
    </div>
  </div>
</body>
</html>
