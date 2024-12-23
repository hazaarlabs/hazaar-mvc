<template>
    <div :class="`vp-doc ${$style.promo}`">
      <h1 :class="$style.hed">Build fast, scalable real-time applications with Hazaar</h1>
      <div :class="$style.subhed">A modern framework designed to transform web development. </div>
      <div :class="$style.columns">
        <div :class="$style.column">
          <span :class="$style['column-header']">Efficient Code Organization</span>
          <span :class="$style['column-body']">Hazaar provides a structured and efficient way to organize your code allowing you to achieve clean, modular code that is easier to maintain and collaborate on.</span>
        </div>
        <div :class="$style.column">
          <span :class="$style['column-header']">Seamless Database Integration</span>
          <span :class="$style['column-body']">Take the hassle out of working with databases by using the integrated Object-Relational Mapping (ORM) which allows effortless database interaction by abstracting away the complexities of SQL queries.</span>
        </div>
        <div :class="$style.column">
          <span :class="$style['column-header']">Real-Time Communication</span>
          <span :class="$style['column-body']">Build highly interactive applications that deliver real-time updates to users using the built-in WebSocket server that enables seamless, bidirectional communication between the client and the server.</span>
        </div>
      </div>
      <a :class="$style.button" href="/guide/getting-started">Get started with Hazaar →</a>
    </div>
  </template>
  
  <style scoped>
  
  h1 {
    border-top: none;
  }
  
  </style>
  
  <style module>
  
  .promo {
    margin: 96px auto 0 auto;
    max-width: 1152px;
    padding: 0 24px;
    text-align: center;
  }
  
  .column {
    margin: 2rem 0;
  }
  
  .column-header {
    font-weight: 600;
  }
  
  .hed,
  .subhed,
  .column {
    padding: 0 24px;
  }
  
  .hed,
  .subhed,
  .column-header {
    text-wrap: balance;
  }
  
  .subhed {
    margin-top: 1rem;
    opacity: 0.7;
  }
  
  a.button {
    display: inline-block;
    border: 1px solid transparent;
    text-align: center;
    font-weight: 600;
    white-space: nowrap;
    background-color: var(--vp-c-neutral);
    color: var(--vp-c-neutral-inverse);
    border-radius: 20px;
    padding: 0 20px;
    line-height: 38px;
    font-size: 14px;
    margin: 0 24px;
  }
  
  a.button:hover {
    text-decoration: none;;
  }
  
  @media (min-width: 640px) {
    .promo {
      padding: 0 48px;
    }
  }
  
  @media (min-width: 768px) {
    .promo {
      margin-top: 128px;
    }
  }
  
  @media (max-width: 959px) {
    .promo {
      max-width: 768px;
    }
    .column-header::after {
      content: " · ";
    }
    .columns {
      text-align: initial;
    }
  }
  
  @media (min-width: 960px) {
    .promo {
      padding: 0 64px;
    }
    .subhed {
      margin-top: 0.5rem;
    }
    .columns {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
    }
    .column {
      margin: 4rem 0;
    }
    .column-header {
      margin-bottom: 0.5rem;
    }
    .column-header,
    .column-body {
      display: block;
    }
  }
  
  </style>