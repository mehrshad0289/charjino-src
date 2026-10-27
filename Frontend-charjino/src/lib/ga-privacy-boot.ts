/** Client boot: sanitize page_location before gtag config and collect hits. */
export const GA_PRIVACY_BOOT = `(function(){
var U='نامعلوم';
var HOST=/(^|\\.)(google-analytics\\.com|analytics\\.google\\.com|googletagmanager\\.com)$/;
function sens(k){return /^(e-?mail|mail|phone|mobile|tel|sms|name|first[_-]?name|last[_-]?name|full[_-]?name|national[_-]?code|national[_-]?id|order(_?id|_?number|_?code)?|number|token|otp|pass(word)?|pin|authority|ref(_)?id|track(ing|_?id)?|user(_?id)?|player(_?id)?|phone[_-]?number|mobile[_-]?number|username|code)$/i.test(String(k));}
function path(p){var x=String(p||'').split('/');function hit(a,b,skip){var i=1;if(x[i]!==a)return;i+=1;if(b){if(x[i]!==b)return;i+=1;}if(x[i]&&x[i]!==skip)x[i]=U;}hit('profile','orders');hit('profile','tickets','new');hit('invoices');hit('orders');hit('support');hit('users');return x.join('/')||'/';}
function loc(raw){try{var href=raw?String(raw):location.href;var u=new URL(href,location.origin);u.pathname=path(u.pathname);var n=new URLSearchParams();u.searchParams.forEach(function(v,k){n.set(k,sens(k)?U:v);});u.search=n.toString();u.hash='';return u.toString();}catch(e){return raw||location.href;}}
function scrub(u){try{var url=new URL(String(u),location.origin);if(!HOST.test(url.hostname))return u;if(url.searchParams.has('dl'))url.searchParams.set('dl',loc(url.searchParams.get('dl')));if(url.searchParams.has('dr'))url.searchParams.set('dr',loc(url.searchParams.get('dr')));if(url.searchParams.has('dp')){var p=new URL(url.searchParams.get('dp')||'/',location.origin);p.pathname=path(p.pathname);var n=new URLSearchParams();p.searchParams.forEach(function(v,k){n.set(k,sens(k)?U:v);});url.searchParams.set('dp',p.pathname+(n.toString()?'?'+n.toString():''));}return url.toString();}catch(e){return u;}}
window.dataLayer=window.dataLayer||[];
function gtag(){window.dataLayer.push(arguments);}
window.gtag=gtag;
gtag('set',{anonymize_ip:true,page_location:loc()});
var ps=history.pushState;var rs=history.replaceState;
history.pushState=function(s,t,u){if(u)gtag('set',{anonymize_ip:true,page_location:loc(new URL(String(u),location.href).href)});return ps.apply(this,arguments);};
history.replaceState=function(s,t,u){if(u)gtag('set',{anonymize_ip:true,page_location:loc(new URL(String(u),location.href).href)});return rs.apply(this,arguments);};
window.addEventListener('popstate',function(){gtag('set',{anonymize_ip:true,page_location:loc()});},true);
if(navigator.sendBeacon){var beacon=navigator.sendBeacon.bind(navigator);navigator.sendBeacon=function(url,data){return beacon(scrub(url),data);};}
if(window.fetch){var origFetch=window.fetch.bind(window);window.fetch=function(input,init){if(typeof input==='string')return origFetch(scrub(input),init);return origFetch(input,init);};}
})();`.replace(/\n/g, '');
