const THEME_INIT_SCRIPT = `(function(){try{var mode=localStorage.getItem('theme-mode')||localStorage.getItem('theme')||'light';var theme=mode==='auto'?(window.matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):(mode==='dark'?'dark':'light');if(theme==='dark'){document.documentElement.classList.add('dark');document.documentElement.setAttribute('data-color-scheme','dark');}else{document.documentElement.classList.remove('dark');document.documentElement.setAttribute('data-color-scheme','light');}}catch(e){}})();`;

export function ThemeScript() {
  return <script dangerouslySetInnerHTML={{ __html: THEME_INIT_SCRIPT }} />;
}
