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
    margin-left: 0.3rem; /* spacing between logo and text */
    line-height: 1.2;
  }

  /* Existing light mode styles */
.logo-text .title {
  font-size: 20px;
  letter-spacing: 0.1em;
  color: #044835; /* green-900 */
  font-family: serif;
}

.logo-text .subtitle {
  font-size: 0.875rem;
  font-weight: 500;
  letter-spacing: 0.1em;
  color: #232426; /* gray-500 */
}

/* Dark mode overrides */
html.dark .logo-text .title {
  color: #6bd369; 
}

html.dark .logo-text .subtitle {
  color: #d1d5db; 
}
</style>
</head>
<body>

<div class="logo-container">
    <img src="{{ asset('brand-logo.png') }}" alt="Marcelino's Logo">  <div class="logo-text">
      <div class="logo-text">
        <div class="title text-green-900 dark:text-white">MARCELINO'S</div>
        <div class="subtitle text-gray-500 dark:text-gray-300">RESORT AND HOTEL</div>
      </div>
  </div>
</div>
