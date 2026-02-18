<style>
  /* Container for logo and text */
  .logo-container {
    display: flex;
    align-items: center;
  }

  /* Logo image */
  .logo-container img {
    height: 3rem; /* 32px */
    width: auto;
    object-fit: contain;
  }

  /* Text container */
  .logo-text {
    margin-left: 0.5rem; /* spacing between logo and text */
    line-height: 1.2;
  }

  /* Main title */
  .logo-text .title {
    font-size: 20px;
    letter-spacing: 0.1em; /* wide spacing */
    color: #044835; /* green-900 */
    font-family: serif;
  }

  /* Subtitle */
  .logo-text .subtitle {
    font-size: 0.875rem; /* text-sm */
    font-weight: 500;
    letter-spacing: 0.1em;
    color: #232426; /* gray-500 */
  }
</style>
</head>
<body>

<div class="logo-container">
    <img src="{{ asset('brand-logo.png') }}" alt="Marcelino's Logo">  <div class="logo-text">
    <div class="title">MARCELINO'S</div>
    <div class="subtitle">RESORT AND HOTEL</div>
  </div>
</div>
