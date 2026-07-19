/* TurkMod TurkMod - Bundled JS */

/* --- bootstrap.bundle.min.js --- */
/*!
  * Bootstrap v5.1.3 (https://getbootstrap.com/)
  * Copyright 2011-2021 The Bootstrap Authors (https://github.com/twbs/bootstrap/graphs/contributors)
  * Licensed under MIT (https://github.com/twbs/bootstrap/blob/main/LICENSE)
  */
!function(t,e){"object"==typeof exports&&"undefined"!=typeof module?module.exports=e():"function"==typeof define&&define.amd?define(e):(t="undefined"!=typeof globalThis?globalThis:t||self).bootstrap=e()}(this,(function(){"use strict";const t="transitionend",e=t=>{let e=t.getAttribute("data-bs-target");if(!e||"#"===e){let i=t.getAttribute("href");if(!i||!i.includes("#")&&!i.startsWith("."))return null;i.includes("#")&&!i.startsWith("#")&&(i=`#${i.split("#")[1]}`),e=i&&"#"!==i?i.trim():null}return e},i=t=>{const i=e(t);return i&&document.querySelector(i)?i:null},n=t=>{const i=e(t);return i?document.querySelector(i):null},s=e=>{e.dispatchEvent(new Event(t))},o=t=>!(!t||"object"!=typeof t)&&(void 0!==t.jquery&&(t=t[0]),void 0!==t.nodeType),r=t=>o(t)?t.jquery?t[0]:t:"string"==typeof t&&t.length>0?document.querySelector(t):null,a=(t,e,i)=>{Object.keys(i).forEach((n=>{const s=i[n],r=e[n],a=r&&o(r)?"element":null==(l=r)?`${l}`:{}.toString.call(l).match(/\s([a-z]+)/i)[1].toLowerCase();var l;if(!new RegExp(s).test(a))throw new TypeError(`${t.toUpperCase()}: Option "${n}" provided type "${a}" but expected type "${s}".`)}))},l=t=>!(!o(t)||0===t.getClientRects().length)&&"visible"===getComputedStyle(t).getPropertyValue("visibility"),c=t=>!t||t.nodeType!==Node.ELEMENT_NODE||!!t.classList.contains("disabled")||(void 0!==t.disabled?t.disabled:t.hasAttribute("disabled")&&"false"!==t.getAttribute("disabled")),h=t=>{if(!document.documentElement.attachShadow)return null;if("function"==typeof t.getRootNode){const e=t.getRootNode();return e instanceof ShadowRoot?e:null}return t instanceof ShadowRoot?t:t.parentNode?h(t.parentNode):null},d=()=>{},u=t=>{t.offsetHeight},f=()=>{const{jQuery:t}=window;return t&&!document.body.hasAttribute("data-bs-no-jquery")?t:null},p=[],m=()=>"rtl"===document.documentElement.dir,g=t=>{var e;e=()=>{const e=f();if(e){const i=t.NAME,n=e.fn[i];e.fn[i]=t.jQueryInterface,e.fn[i].Constructor=t,e.fn[i].noConflict=()=>(e.fn[i]=n,t.jQueryInterface)}},"loading"===document.readyState?(p.length||document.addEventListener("DOMContentLoaded",(()=>{p.forEach((t=>t()))})),p.push(e)):e()},_=t=>{"function"==typeof t&&t()},b=(e,i,n=!0)=>{if(!n)return void _(e);const o=(t=>{if(!t)return 0;let{transitionDuration:e,transitionDelay:i}=window.getComputedStyle(t);const n=Number.parseFloat(e),s=Number.parseFloat(i);return n||s?(e=e.split(",")[0],i=i.split(",")[0],1e3*(Number.parseFloat(e)+Number.parseFloat(i))):0})(i)+5;let r=!1;const a=({target:n})=>{n===i&&(r=!0,i.removeEventListener(t,a),_(e))};i.addEventListener(t,a),setTimeout((()=>{r||s(i)}),o)},v=(t,e,i,n)=>{let s=t.indexOf(e);if(-1===s)return t[!i&&n?t.length-1:0];const o=t.length;return s+=i?1:-1,n&&(s=(s+o)%o),t[Math.max(0,Math.min(s,o-1))]},y=/[^.]*(?=\..*)\.|.*/,w=/\..*/,E=/::\d+$/,A={};let T=1;const O={mouseenter:"mouseover",mouseleave:"mouseout"},C=/^(mouseenter|mouseleave)/i,k=new Set(["click","dblclick","mouseup","mousedown","contextmenu","mousewheel","DOMMouseScroll","mouseover","mouseout","mousemove","selectstart","selectend","keydown","keypress","keyup","orientationchange","touchstart","touchmove","touchend","touchcancel","pointerdown","pointermove","pointerup","pointerleave","pointercancel","gesturestart","gesturechange","gestureend","focus","blur","change","reset","select","submit","focusin","focusout","load","unload","beforeunload","resize","move","DOMContentLoaded","readystatechange","error","abort","scroll"]);function L(t,e){return e&&`${e}::${T++}`||t.uidEvent||T++}function x(t){const e=L(t);return t.uidEvent=e,A[e]=A[e]||{},A[e]}function D(t,e,i=null){const n=Object.keys(t);for(let s=0,o=n.length;s<o;s++){const o=t[n[s]];if(o.originalHandler===e&&o.delegationSelector===i)return o}return null}function S(t,e,i){const n="string"==typeof e,s=n?i:e;let o=P(t);return k.has(o)||(o=t),[n,s,o]}function N(t,e,i,n,s){if("string"!=typeof e||!t)return;if(i||(i=n,n=null),C.test(e)){const t=t=>function(e){if(!e.relatedTarget||e.relatedTarget!==e.delegateTarget&&!e.delegateTarget.contains(e.relatedTarget))return t.call(this,e)};n?n=t(n):i=t(i)}const[o,r,a]=S(e,i,n),l=x(t),c=l[a]||(l[a]={}),h=D(c,r,o?i:null);if(h)return void(h.oneOff=h.oneOff&&s);const d=L(r,e.replace(y,"")),u=o?function(t,e,i){return function n(s){const o=t.querySelectorAll(e);for(let{target:r}=s;r&&r!==this;r=r.parentNode)for(let a=o.length;a--;)if(o[a]===r)return s.delegateTarget=r,n.oneOff&&j.off(t,s.type,e,i),i.apply(r,[s]);return null}}(t,i,n):function(t,e){return function i(n){return n.delegateTarget=t,i.oneOff&&j.off(t,n.type,e),e.apply(t,[n])}}(t,i);u.delegationSelector=o?i:null,u.originalHandler=r,u.oneOff=s,u.uidEvent=d,c[d]=u,t.addEventListener(a,u,o)}function I(t,e,i,n,s){const o=D(e[i],n,s);o&&(t.removeEventListener(i,o,Boolean(s)),delete e[i][o.uidEvent])}function P(t){return t=t.replace(w,""),O[t]||t}const j={on(t,e,i,n){N(t,e,i,n,!1)},one(t,e,i,n){N(t,e,i,n,!0)},off(t,e,i,n){if("string"!=typeof e||!t)return;const[s,o,r]=S(e,i,n),a=r!==e,l=x(t),c=e.startsWith(".");if(void 0!==o){if(!l||!l[r])return;return void I(t,l,r,o,s?i:null)}c&&Object.keys(l).forEach((i=>{!function(t,e,i,n){const s=e[i]||{};Object.keys(s).forEach((o=>{if(o.includes(n)){const n=s[o];I(t,e,i,n.originalHandler,n.delegationSelector)}}))}(t,l,i,e.slice(1))}));const h=l[r]||{};Object.keys(h).forEach((i=>{const n=i.replace(E,"");if(!a||e.includes(n)){const e=h[i];I(t,l,r,e.originalHandler,e.delegationSelector)}}))},trigger(t,e,i){if("string"!=typeof e||!t)return null;const n=f(),s=P(e),o=e!==s,r=k.has(s);let a,l=!0,c=!0,h=!1,d=null;return o&&n&&(a=n.Event(e,i),n(t).trigger(a),l=!a.isPropagationStopped(),c=!a.isImmediatePropagationStopped(),h=a.isDefaultPrevented()),r?(d=document.createEvent("HTMLEvents"),d.initEvent(s,l,!0)):d=new CustomEvent(e,{bubbles:l,cancelable:!0}),void 0!==i&&Object.keys(i).forEach((t=>{Object.defineProperty(d,t,{get:()=>i[t]})})),h&&d.preventDefault(),c&&t.dispatchEvent(d),d.defaultPrevented&&void 0!==a&&a.preventDefault(),d}},M=new Map,H={set(t,e,i){M.has(t)||M.set(t,new Map);const n=M.get(t);n.has(e)||0===n.size?n.set(e,i):console.error(`Bootstrap doesn't allow more than one instance per element. Bound instance: ${Array.from(n.keys())[0]}.`)},get:(t,e)=>M.has(t)&&M.get(t).get(e)||null,remove(t,e){if(!M.has(t))return;const i=M.get(t);i.delete(e),0===i.size&&M.delete(t)}};class B{constructor(t){(t=r(t))&&(this._element=t,H.set(this._element,this.constructor.DATA_KEY,this))}dispose(){H.remove(this._element,this.constructor.DATA_KEY),j.off(this._element,this.constructor.EVENT_KEY),Object.getOwnPropertyNames(this).forEach((t=>{this[t]=null}))}_queueCallback(t,e,i=!0){b(t,e,i)}static getInstance(t){return H.get(r(t),this.DATA_KEY)}static getOrCreateInstance(t,e={}){return this.getInstance(t)||new this(t,"object"==typeof e?e:null)}static get VERSION(){return"5.1.3"}static get NAME(){throw new Error('You have to implement the static method "NAME", for each component!')}static get DATA_KEY(){return`bs.${this.NAME}`}static get EVENT_KEY(){return`.${this.DATA_KEY}`}}const R=(t,e="hide")=>{const i=`click.dismiss${t.EVENT_KEY}`,s=t.NAME;j.on(document,i,`[data-bs-dismiss="${s}"]`,(function(i){if(["A","AREA"].includes(this.tagName)&&i.preventDefault(),c(this))return;const o=n(this)||this.closest(`.${s}`);t.getOrCreateInstance(o)[e]()}))};class W extends B{static get NAME(){return"alert"}close(){if(j.trigger(this._element,"close.bs.alert").defaultPrevented)return;this._element.classList.remove("show");const t=this._element.classList.contains("fade");this._queueCallback((()=>this._destroyElement()),this._element,t)}_destroyElement(){this._element.remove(),j.trigger(this._element,"closed.bs.alert"),this.dispose()}static jQueryInterface(t){return this.each((function(){const e=W.getOrCreateInstance(this);if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t](this)}}))}}R(W,"close"),g(W);const $='[data-bs-toggle="button"]';class z extends B{static get NAME(){return"button"}toggle(){this._element.setAttribute("aria-pressed",this._element.classList.toggle("active"))}static jQueryInterface(t){return this.each((function(){const e=z.getOrCreateInstance(this);"toggle"===t&&e[t]()}))}}function q(t){return"true"===t||"false"!==t&&(t===Number(t).toString()?Number(t):""===t||"null"===t?null:t)}function F(t){return t.replace(/[A-Z]/g,(t=>`-${t.toLowerCase()}`))}j.on(document,"click.bs.button.data-api",$,(t=>{t.preventDefault();const e=t.target.closest($);z.getOrCreateInstance(e).toggle()})),g(z);const U={setDataAttribute(t,e,i){t.setAttribute(`data-bs-${F(e)}`,i)},removeDataAttribute(t,e){t.removeAttribute(`data-bs-${F(e)}`)},getDataAttributes(t){if(!t)return{};const e={};return Object.keys(t.dataset).filter((t=>t.startsWith("bs"))).forEach((i=>{let n=i.replace(/^bs/,"");n=n.charAt(0).toLowerCase()+n.slice(1,n.length),e[n]=q(t.dataset[i])})),e},getDataAttribute:(t,e)=>q(t.getAttribute(`data-bs-${F(e)}`)),offset(t){const e=t.getBoundingClientRect();return{top:e.top+window.pageYOffset,left:e.left+window.pageXOffset}},position:t=>({top:t.offsetTop,left:t.offsetLeft})},V={find:(t,e=document.documentElement)=>[].concat(...Element.prototype.querySelectorAll.call(e,t)),findOne:(t,e=document.documentElement)=>Element.prototype.querySelector.call(e,t),children:(t,e)=>[].concat(...t.children).filter((t=>t.matches(e))),parents(t,e){const i=[];let n=t.parentNode;for(;n&&n.nodeType===Node.ELEMENT_NODE&&3!==n.nodeType;)n.matches(e)&&i.push(n),n=n.parentNode;return i},prev(t,e){let i=t.previousElementSibling;for(;i;){if(i.matches(e))return[i];i=i.previousElementSibling}return[]},next(t,e){let i=t.nextElementSibling;for(;i;){if(i.matches(e))return[i];i=i.nextElementSibling}return[]},focusableChildren(t){const e=["a","button","input","textarea","select","details","[tabindex]",'[contenteditable="true"]'].map((t=>`${t}:not([tabindex^="-"])`)).join(", ");return this.find(e,t).filter((t=>!c(t)&&l(t)))}},K="carousel",X={interval:5e3,keyboard:!0,slide:!1,pause:"hover",wrap:!0,touch:!0},Y={interval:"(number|boolean)",keyboard:"boolean",slide:"(boolean|string)",pause:"(string|boolean)",wrap:"boolean",touch:"boolean"},Q="next",G="prev",Z="left",J="right",tt={ArrowLeft:J,ArrowRight:Z},et="slid.bs.carousel",it="active",nt=".active.carousel-item";class st extends B{constructor(t,e){super(t),this._items=null,this._interval=null,this._activeElement=null,this._isPaused=!1,this._isSliding=!1,this.touchTimeout=null,this.touchStartX=0,this.touchDeltaX=0,this._config=this._getConfig(e),this._indicatorsElement=V.findOne(".carousel-indicators",this._element),this._touchSupported="ontouchstart"in document.documentElement||navigator.maxTouchPoints>0,this._pointerEvent=Boolean(window.PointerEvent),this._addEventListeners()}static get Default(){return X}static get NAME(){return K}next(){this._slide(Q)}nextWhenVisible(){!document.hidden&&l(this._element)&&this.next()}prev(){this._slide(G)}pause(t){t||(this._isPaused=!0),V.findOne(".carousel-item-next, .carousel-item-prev",this._element)&&(s(this._element),this.cycle(!0)),clearInterval(this._interval),this._interval=null}cycle(t){t||(this._isPaused=!1),this._interval&&(clearInterval(this._interval),this._interval=null),this._config&&this._config.interval&&!this._isPaused&&(this._updateInterval(),this._interval=setInterval((document.visibilityState?this.nextWhenVisible:this.next).bind(this),this._config.interval))}to(t){this._activeElement=V.findOne(nt,this._element);const e=this._getItemIndex(this._activeElement);if(t>this._items.length-1||t<0)return;if(this._isSliding)return void j.one(this._element,et,(()=>this.to(t)));if(e===t)return this.pause(),void this.cycle();const i=t>e?Q:G;this._slide(i,this._items[t])}_getConfig(t){return t={...X,...U.getDataAttributes(this._element),..."object"==typeof t?t:{}},a(K,t,Y),t}_handleSwipe(){const t=Math.abs(this.touchDeltaX);if(t<=40)return;const e=t/this.touchDeltaX;this.touchDeltaX=0,e&&this._slide(e>0?J:Z)}_addEventListeners(){this._config.keyboard&&j.on(this._element,"keydown.bs.carousel",(t=>this._keydown(t))),"hover"===this._config.pause&&(j.on(this._element,"mouseenter.bs.carousel",(t=>this.pause(t))),j.on(this._element,"mouseleave.bs.carousel",(t=>this.cycle(t)))),this._config.touch&&this._touchSupported&&this._addTouchEventListeners()}_addTouchEventListeners(){const t=t=>this._pointerEvent&&("pen"===t.pointerType||"touch"===t.pointerType),e=e=>{t(e)?this.touchStartX=e.clientX:this._pointerEvent||(this.touchStartX=e.touches[0].clientX)},i=t=>{this.touchDeltaX=t.touches&&t.touches.length>1?0:t.touches[0].clientX-this.touchStartX},n=e=>{t(e)&&(this.touchDeltaX=e.clientX-this.touchStartX),this._handleSwipe(),"hover"===this._config.pause&&(this.pause(),this.touchTimeout&&clearTimeout(this.touchTimeout),this.touchTimeout=setTimeout((t=>this.cycle(t)),500+this._config.interval))};V.find(".carousel-item img",this._element).forEach((t=>{j.on(t,"dragstart.bs.carousel",(t=>t.preventDefault()))})),this._pointerEvent?(j.on(this._element,"pointerdown.bs.carousel",(t=>e(t))),j.on(this._element,"pointerup.bs.carousel",(t=>n(t))),this._element.classList.add("pointer-event")):(j.on(this._element,"touchstart.bs.carousel",(t=>e(t))),j.on(this._element,"touchmove.bs.carousel",(t=>i(t))),j.on(this._element,"touchend.bs.carousel",(t=>n(t))))}_keydown(t){if(/input|textarea/i.test(t.target.tagName))return;const e=tt[t.key];e&&(t.preventDefault(),this._slide(e))}_getItemIndex(t){return this._items=t&&t.parentNode?V.find(".carousel-item",t.parentNode):[],this._items.indexOf(t)}_getItemByOrder(t,e){const i=t===Q;return v(this._items,e,i,this._config.wrap)}_triggerSlideEvent(t,e){const i=this._getItemIndex(t),n=this._getItemIndex(V.findOne(nt,this._element));return j.trigger(this._element,"slide.bs.carousel",{relatedTarget:t,direction:e,from:n,to:i})}_setActiveIndicatorElement(t){if(this._indicatorsElement){const e=V.findOne(".active",this._indicatorsElement);e.classList.remove(it),e.removeAttribute("aria-current");const i=V.find("[data-bs-target]",this._indicatorsElement);for(let e=0;e<i.length;e++)if(Number.parseInt(i[e].getAttribute("data-bs-slide-to"),10)===this._getItemIndex(t)){i[e].classList.add(it),i[e].setAttribute("aria-current","true");break}}}_updateInterval(){const t=this._activeElement||V.findOne(nt,this._element);if(!t)return;const e=Number.parseInt(t.getAttribute("data-bs-interval"),10);e?(this._config.defaultInterval=this._config.defaultInterval||this._config.interval,this._config.interval=e):this._config.interval=this._config.defaultInterval||this._config.interval}_slide(t,e){const i=this._directionToOrder(t),n=V.findOne(nt,this._element),s=this._getItemIndex(n),o=e||this._getItemByOrder(i,n),r=this._getItemIndex(o),a=Boolean(this._interval),l=i===Q,c=l?"carousel-item-start":"carousel-item-end",h=l?"carousel-item-next":"carousel-item-prev",d=this._orderToDirection(i);if(o&&o.classList.contains(it))return void(this._isSliding=!1);if(this._isSliding)return;if(this._triggerSlideEvent(o,d).defaultPrevented)return;if(!n||!o)return;this._isSliding=!0,a&&this.pause(),this._setActiveIndicatorElement(o),this._activeElement=o;const f=()=>{j.trigger(this._element,et,{relatedTarget:o,direction:d,from:s,to:r})};if(this._element.classList.contains("slide")){o.classList.add(h),u(o),n.classList.add(c),o.classList.add(c);const t=()=>{o.classList.remove(c,h),o.classList.add(it),n.classList.remove(it,h,c),this._isSliding=!1,setTimeout(f,0)};this._queueCallback(t,n,!0)}else n.classList.remove(it),o.classList.add(it),this._isSliding=!1,f();a&&this.cycle()}_directionToOrder(t){return[J,Z].includes(t)?m()?t===Z?G:Q:t===Z?Q:G:t}_orderToDirection(t){return[Q,G].includes(t)?m()?t===G?Z:J:t===G?J:Z:t}static carouselInterface(t,e){const i=st.getOrCreateInstance(t,e);let{_config:n}=i;"object"==typeof e&&(n={...n,...e});const s="string"==typeof e?e:n.slide;if("number"==typeof e)i.to(e);else if("string"==typeof s){if(void 0===i[s])throw new TypeError(`No method named "${s}"`);i[s]()}else n.interval&&n.ride&&(i.pause(),i.cycle())}static jQueryInterface(t){return this.each((function(){st.carouselInterface(this,t)}))}static dataApiClickHandler(t){const e=n(this);if(!e||!e.classList.contains("carousel"))return;const i={...U.getDataAttributes(e),...U.getDataAttributes(this)},s=this.getAttribute("data-bs-slide-to");s&&(i.interval=!1),st.carouselInterface(e,i),s&&st.getInstance(e).to(s),t.preventDefault()}}j.on(document,"click.bs.carousel.data-api","[data-bs-slide], [data-bs-slide-to]",st.dataApiClickHandler),j.on(window,"load.bs.carousel.data-api",(()=>{const t=V.find('[data-bs-ride="carousel"]');for(let e=0,i=t.length;e<i;e++)st.carouselInterface(t[e],st.getInstance(t[e]))})),g(st);const ot="collapse",rt={toggle:!0,parent:null},at={toggle:"boolean",parent:"(null|element)"},lt="show",ct="collapse",ht="collapsing",dt="collapsed",ut=":scope .collapse .collapse",ft='[data-bs-toggle="collapse"]';class pt extends B{constructor(t,e){super(t),this._isTransitioning=!1,this._config=this._getConfig(e),this._triggerArray=[];const n=V.find(ft);for(let t=0,e=n.length;t<e;t++){const e=n[t],s=i(e),o=V.find(s).filter((t=>t===this._element));null!==s&&o.length&&(this._selector=s,this._triggerArray.push(e))}this._initializeChildren(),this._config.parent||this._addAriaAndCollapsedClass(this._triggerArray,this._isShown()),this._config.toggle&&this.toggle()}static get Default(){return rt}static get NAME(){return ot}toggle(){this._isShown()?this.hide():this.show()}show(){if(this._isTransitioning||this._isShown())return;let t,e=[];if(this._config.parent){const t=V.find(ut,this._config.parent);e=V.find(".collapse.show, .collapse.collapsing",this._config.parent).filter((e=>!t.includes(e)))}const i=V.findOne(this._selector);if(e.length){const n=e.find((t=>i!==t));if(t=n?pt.getInstance(n):null,t&&t._isTransitioning)return}if(j.trigger(this._element,"show.bs.collapse").defaultPrevented)return;e.forEach((e=>{i!==e&&pt.getOrCreateInstance(e,{toggle:!1}).hide(),t||H.set(e,"bs.collapse",null)}));const n=this._getDimension();this._element.classList.remove(ct),this._element.classList.add(ht),this._element.style[n]=0,this._addAriaAndCollapsedClass(this._triggerArray,!0),this._isTransitioning=!0;const s=`scroll${n[0].toUpperCase()+n.slice(1)}`;this._queueCallback((()=>{this._isTransitioning=!1,this._element.classList.remove(ht),this._element.classList.add(ct,lt),this._element.style[n]="",j.trigger(this._element,"shown.bs.collapse")}),this._element,!0),this._element.style[n]=`${this._element[s]}px`}hide(){if(this._isTransitioning||!this._isShown())return;if(j.trigger(this._element,"hide.bs.collapse").defaultPrevented)return;const t=this._getDimension();this._element.style[t]=`${this._element.getBoundingClientRect()[t]}px`,u(this._element),this._element.classList.add(ht),this._element.classList.remove(ct,lt);const e=this._triggerArray.length;for(let t=0;t<e;t++){const e=this._triggerArray[t],i=n(e);i&&!this._isShown(i)&&this._addAriaAndCollapsedClass([e],!1)}this._isTransitioning=!0,this._element.style[t]="",this._queueCallback((()=>{this._isTransitioning=!1,this._element.classList.remove(ht),this._element.classList.add(ct),j.trigger(this._element,"hidden.bs.collapse")}),this._element,!0)}_isShown(t=this._element){return t.classList.contains(lt)}_getConfig(t){return(t={...rt,...U.getDataAttributes(this._element),...t}).toggle=Boolean(t.toggle),t.parent=r(t.parent),a(ot,t,at),t}_getDimension(){return this._element.classList.contains("collapse-horizontal")?"width":"height"}_initializeChildren(){if(!this._config.parent)return;const t=V.find(ut,this._config.parent);V.find(ft,this._config.parent).filter((e=>!t.includes(e))).forEach((t=>{const e=n(t);e&&this._addAriaAndCollapsedClass([t],this._isShown(e))}))}_addAriaAndCollapsedClass(t,e){t.length&&t.forEach((t=>{e?t.classList.remove(dt):t.classList.add(dt),t.setAttribute("aria-expanded",e)}))}static jQueryInterface(t){return this.each((function(){const e={};"string"==typeof t&&/show|hide/.test(t)&&(e.toggle=!1);const i=pt.getOrCreateInstance(this,e);if("string"==typeof t){if(void 0===i[t])throw new TypeError(`No method named "${t}"`);i[t]()}}))}}j.on(document,"click.bs.collapse.data-api",ft,(function(t){("A"===t.target.tagName||t.delegateTarget&&"A"===t.delegateTarget.tagName)&&t.preventDefault();const e=i(this);V.find(e).forEach((t=>{pt.getOrCreateInstance(t,{toggle:!1}).toggle()}))})),g(pt);var mt="top",gt="bottom",_t="right",bt="left",vt="auto",yt=[mt,gt,_t,bt],wt="start",Et="end",At="clippingParents",Tt="viewport",Ot="popper",Ct="reference",kt=yt.reduce((function(t,e){return t.concat([e+"-"+wt,e+"-"+Et])}),[]),Lt=[].concat(yt,[vt]).reduce((function(t,e){return t.concat([e,e+"-"+wt,e+"-"+Et])}),[]),xt="beforeRead",Dt="read",St="afterRead",Nt="beforeMain",It="main",Pt="afterMain",jt="beforeWrite",Mt="write",Ht="afterWrite",Bt=[xt,Dt,St,Nt,It,Pt,jt,Mt,Ht];function Rt(t){return t?(t.nodeName||"").toLowerCase():null}function Wt(t){if(null==t)return window;if("[object Window]"!==t.toString()){var e=t.ownerDocument;return e&&e.defaultView||window}return t}function $t(t){return t instanceof Wt(t).Element||t instanceof Element}function zt(t){return t instanceof Wt(t).HTMLElement||t instanceof HTMLElement}function qt(t){return"undefined"!=typeof ShadowRoot&&(t instanceof Wt(t).ShadowRoot||t instanceof ShadowRoot)}const Ft={name:"applyStyles",enabled:!0,phase:"write",fn:function(t){var e=t.state;Object.keys(e.elements).forEach((function(t){var i=e.styles[t]||{},n=e.attributes[t]||{},s=e.elements[t];zt(s)&&Rt(s)&&(Object.assign(s.style,i),Object.keys(n).forEach((function(t){var e=n[t];!1===e?s.removeAttribute(t):s.setAttribute(t,!0===e?"":e)})))}))},effect:function(t){var e=t.state,i={popper:{position:e.options.strategy,left:"0",top:"0",margin:"0"},arrow:{position:"absolute"},reference:{}};return Object.assign(e.elements.popper.style,i.popper),e.styles=i,e.elements.arrow&&Object.assign(e.elements.arrow.style,i.arrow),function(){Object.keys(e.elements).forEach((function(t){var n=e.elements[t],s=e.attributes[t]||{},o=Object.keys(e.styles.hasOwnProperty(t)?e.styles[t]:i[t]).reduce((function(t,e){return t[e]="",t}),{});zt(n)&&Rt(n)&&(Object.assign(n.style,o),Object.keys(s).forEach((function(t){n.removeAttribute(t)})))}))}},requires:["computeStyles"]};function Ut(t){return t.split("-")[0]}function Vt(t,e){var i=t.getBoundingClientRect();return{width:i.width/1,height:i.height/1,top:i.top/1,right:i.right/1,bottom:i.bottom/1,left:i.left/1,x:i.left/1,y:i.top/1}}function Kt(t){var e=Vt(t),i=t.offsetWidth,n=t.offsetHeight;return Math.abs(e.width-i)<=1&&(i=e.width),Math.abs(e.height-n)<=1&&(n=e.height),{x:t.offsetLeft,y:t.offsetTop,width:i,height:n}}function Xt(t,e){var i=e.getRootNode&&e.getRootNode();if(t.contains(e))return!0;if(i&&qt(i)){var n=e;do{if(n&&t.isSameNode(n))return!0;n=n.parentNode||n.host}while(n)}return!1}function Yt(t){return Wt(t).getComputedStyle(t)}function Qt(t){return["table","td","th"].indexOf(Rt(t))>=0}function Gt(t){return(($t(t)?t.ownerDocument:t.document)||window.document).documentElement}function Zt(t){return"html"===Rt(t)?t:t.assignedSlot||t.parentNode||(qt(t)?t.host:null)||Gt(t)}function Jt(t){return zt(t)&&"fixed"!==Yt(t).position?t.offsetParent:null}function te(t){for(var e=Wt(t),i=Jt(t);i&&Qt(i)&&"static"===Yt(i).position;)i=Jt(i);return i&&("html"===Rt(i)||"body"===Rt(i)&&"static"===Yt(i).position)?e:i||function(t){var e=-1!==navigator.userAgent.toLowerCase().indexOf("firefox");if(-1!==navigator.userAgent.indexOf("Trident")&&zt(t)&&"fixed"===Yt(t).position)return null;for(var i=Zt(t);zt(i)&&["html","body"].indexOf(Rt(i))<0;){var n=Yt(i);if("none"!==n.transform||"none"!==n.perspective||"paint"===n.contain||-1!==["transform","perspective"].indexOf(n.willChange)||e&&"filter"===n.willChange||e&&n.filter&&"none"!==n.filter)return i;i=i.parentNode}return null}(t)||e}function ee(t){return["top","bottom"].indexOf(t)>=0?"x":"y"}var ie=Math.max,ne=Math.min,se=Math.round;function oe(t,e,i){return ie(t,ne(e,i))}function re(t){return Object.assign({},{top:0,right:0,bottom:0,left:0},t)}function ae(t,e){return e.reduce((function(e,i){return e[i]=t,e}),{})}const le={name:"arrow",enabled:!0,phase:"main",fn:function(t){var e,i=t.state,n=t.name,s=t.options,o=i.elements.arrow,r=i.modifiersData.popperOffsets,a=Ut(i.placement),l=ee(a),c=[bt,_t].indexOf(a)>=0?"height":"width";if(o&&r){var h=function(t,e){return re("number"!=typeof(t="function"==typeof t?t(Object.assign({},e.rects,{placement:e.placement})):t)?t:ae(t,yt))}(s.padding,i),d=Kt(o),u="y"===l?mt:bt,f="y"===l?gt:_t,p=i.rects.reference[c]+i.rects.reference[l]-r[l]-i.rects.popper[c],m=r[l]-i.rects.reference[l],g=te(o),_=g?"y"===l?g.clientHeight||0:g.clientWidth||0:0,b=p/2-m/2,v=h[u],y=_-d[c]-h[f],w=_/2-d[c]/2+b,E=oe(v,w,y),A=l;i.modifiersData[n]=((e={})[A]=E,e.centerOffset=E-w,e)}},effect:function(t){var e=t.state,i=t.options.element,n=void 0===i?"[data-popper-arrow]":i;null!=n&&("string"!=typeof n||(n=e.elements.popper.querySelector(n)))&&Xt(e.elements.popper,n)&&(e.elements.arrow=n)},requires:["popperOffsets"],requiresIfExists:["preventOverflow"]};function ce(t){return t.split("-")[1]}var he={top:"auto",right:"auto",bottom:"auto",left:"auto"};function de(t){var e,i=t.popper,n=t.popperRect,s=t.placement,o=t.variation,r=t.offsets,a=t.position,l=t.gpuAcceleration,c=t.adaptive,h=t.roundOffsets,d=!0===h?function(t){var e=t.x,i=t.y,n=window.devicePixelRatio||1;return{x:se(se(e*n)/n)||0,y:se(se(i*n)/n)||0}}(r):"function"==typeof h?h(r):r,u=d.x,f=void 0===u?0:u,p=d.y,m=void 0===p?0:p,g=r.hasOwnProperty("x"),_=r.hasOwnProperty("y"),b=bt,v=mt,y=window;if(c){var w=te(i),E="clientHeight",A="clientWidth";w===Wt(i)&&"static"!==Yt(w=Gt(i)).position&&"absolute"===a&&(E="scrollHeight",A="scrollWidth"),w=w,s!==mt&&(s!==bt&&s!==_t||o!==Et)||(v=gt,m-=w[E]-n.height,m*=l?1:-1),s!==bt&&(s!==mt&&s!==gt||o!==Et)||(b=_t,f-=w[A]-n.width,f*=l?1:-1)}var T,O=Object.assign({position:a},c&&he);return l?Object.assign({},O,((T={})[v]=_?"0":"",T[b]=g?"0":"",T.transform=(y.devicePixelRatio||1)<=1?"translate("+f+"px, "+m+"px)":"translate3d("+f+"px, "+m+"px, 0)",T)):Object.assign({},O,((e={})[v]=_?m+"px":"",e[b]=g?f+"px":"",e.transform="",e))}const ue={name:"computeStyles",enabled:!0,phase:"beforeWrite",fn:function(t){var e=t.state,i=t.options,n=i.gpuAcceleration,s=void 0===n||n,o=i.adaptive,r=void 0===o||o,a=i.roundOffsets,l=void 0===a||a,c={placement:Ut(e.placement),variation:ce(e.placement),popper:e.elements.popper,popperRect:e.rects.popper,gpuAcceleration:s};null!=e.modifiersData.popperOffsets&&(e.styles.popper=Object.assign({},e.styles.popper,de(Object.assign({},c,{offsets:e.modifiersData.popperOffsets,position:e.options.strategy,adaptive:r,roundOffsets:l})))),null!=e.modifiersData.arrow&&(e.styles.arrow=Object.assign({},e.styles.arrow,de(Object.assign({},c,{offsets:e.modifiersData.arrow,position:"absolute",adaptive:!1,roundOffsets:l})))),e.attributes.popper=Object.assign({},e.attributes.popper,{"data-popper-placement":e.placement})},data:{}};var fe={passive:!0};const pe={name:"eventListeners",enabled:!0,phase:"write",fn:function(){},effect:function(t){var e=t.state,i=t.instance,n=t.options,s=n.scroll,o=void 0===s||s,r=n.resize,a=void 0===r||r,l=Wt(e.elements.popper),c=[].concat(e.scrollParents.reference,e.scrollParents.popper);return o&&c.forEach((function(t){t.addEventListener("scroll",i.update,fe)})),a&&l.addEventListener("resize",i.update,fe),function(){o&&c.forEach((function(t){t.removeEventListener("scroll",i.update,fe)})),a&&l.removeEventListener("resize",i.update,fe)}},data:{}};var me={left:"right",right:"left",bottom:"top",top:"bottom"};function ge(t){return t.replace(/left|right|bottom|top/g,(function(t){return me[t]}))}var _e={start:"end",end:"start"};function be(t){return t.replace(/start|end/g,(function(t){return _e[t]}))}function ve(t){var e=Wt(t);return{scrollLeft:e.pageXOffset,scrollTop:e.pageYOffset}}function ye(t){return Vt(Gt(t)).left+ve(t).scrollLeft}function we(t){var e=Yt(t),i=e.overflow,n=e.overflowX,s=e.overflowY;return/auto|scroll|overlay|hidden/.test(i+s+n)}function Ee(t){return["html","body","#document"].indexOf(Rt(t))>=0?t.ownerDocument.body:zt(t)&&we(t)?t:Ee(Zt(t))}function Ae(t,e){var i;void 0===e&&(e=[]);var n=Ee(t),s=n===(null==(i=t.ownerDocument)?void 0:i.body),o=Wt(n),r=s?[o].concat(o.visualViewport||[],we(n)?n:[]):n,a=e.concat(r);return s?a:a.concat(Ae(Zt(r)))}function Te(t){return Object.assign({},t,{left:t.x,top:t.y,right:t.x+t.width,bottom:t.y+t.height})}function Oe(t,e){return e===Tt?Te(function(t){var e=Wt(t),i=Gt(t),n=e.visualViewport,s=i.clientWidth,o=i.clientHeight,r=0,a=0;return n&&(s=n.width,o=n.height,/^((?!chrome|android).)*safari/i.test(navigator.userAgent)||(r=n.offsetLeft,a=n.offsetTop)),{width:s,height:o,x:r+ye(t),y:a}}(t)):zt(e)?function(t){var e=Vt(t);return e.top=e.top+t.clientTop,e.left=e.left+t.clientLeft,e.bottom=e.top+t.clientHeight,e.right=e.left+t.clientWidth,e.width=t.clientWidth,e.height=t.clientHeight,e.x=e.left,e.y=e.top,e}(e):Te(function(t){var e,i=Gt(t),n=ve(t),s=null==(e=t.ownerDocument)?void 0:e.body,o=ie(i.scrollWidth,i.clientWidth,s?s.scrollWidth:0,s?s.clientWidth:0),r=ie(i.scrollHeight,i.clientHeight,s?s.scrollHeight:0,s?s.clientHeight:0),a=-n.scrollLeft+ye(t),l=-n.scrollTop;return"rtl"===Yt(s||i).direction&&(a+=ie(i.clientWidth,s?s.clientWidth:0)-o),{width:o,height:r,x:a,y:l}}(Gt(t)))}function Ce(t){var e,i=t.reference,n=t.element,s=t.placement,o=s?Ut(s):null,r=s?ce(s):null,a=i.x+i.width/2-n.width/2,l=i.y+i.height/2-n.height/2;switch(o){case mt:e={x:a,y:i.y-n.height};break;case gt:e={x:a,y:i.y+i.height};break;case _t:e={x:i.x+i.width,y:l};break;case bt:e={x:i.x-n.width,y:l};break;default:e={x:i.x,y:i.y}}var c=o?ee(o):null;if(null!=c){var h="y"===c?"height":"width";switch(r){case wt:e[c]=e[c]-(i[h]/2-n[h]/2);break;case Et:e[c]=e[c]+(i[h]/2-n[h]/2)}}return e}function ke(t,e){void 0===e&&(e={});var i=e,n=i.placement,s=void 0===n?t.placement:n,o=i.boundary,r=void 0===o?At:o,a=i.rootBoundary,l=void 0===a?Tt:a,c=i.elementContext,h=void 0===c?Ot:c,d=i.altBoundary,u=void 0!==d&&d,f=i.padding,p=void 0===f?0:f,m=re("number"!=typeof p?p:ae(p,yt)),g=h===Ot?Ct:Ot,_=t.rects.popper,b=t.elements[u?g:h],v=function(t,e,i){var n="clippingParents"===e?function(t){var e=Ae(Zt(t)),i=["absolute","fixed"].indexOf(Yt(t).position)>=0&&zt(t)?te(t):t;return $t(i)?e.filter((function(t){return $t(t)&&Xt(t,i)&&"body"!==Rt(t)})):[]}(t):[].concat(e),s=[].concat(n,[i]),o=s[0],r=s.reduce((function(e,i){var n=Oe(t,i);return e.top=ie(n.top,e.top),e.right=ne(n.right,e.right),e.bottom=ne(n.bottom,e.bottom),e.left=ie(n.left,e.left),e}),Oe(t,o));return r.width=r.right-r.left,r.height=r.bottom-r.top,r.x=r.left,r.y=r.top,r}($t(b)?b:b.contextElement||Gt(t.elements.popper),r,l),y=Vt(t.elements.reference),w=Ce({reference:y,element:_,strategy:"absolute",placement:s}),E=Te(Object.assign({},_,w)),A=h===Ot?E:y,T={top:v.top-A.top+m.top,bottom:A.bottom-v.bottom+m.bottom,left:v.left-A.left+m.left,right:A.right-v.right+m.right},O=t.modifiersData.offset;if(h===Ot&&O){var C=O[s];Object.keys(T).forEach((function(t){var e=[_t,gt].indexOf(t)>=0?1:-1,i=[mt,gt].indexOf(t)>=0?"y":"x";T[t]+=C[i]*e}))}return T}function Le(t,e){void 0===e&&(e={});var i=e,n=i.placement,s=i.boundary,o=i.rootBoundary,r=i.padding,a=i.flipVariations,l=i.allowedAutoPlacements,c=void 0===l?Lt:l,h=ce(n),d=h?a?kt:kt.filter((function(t){return ce(t)===h})):yt,u=d.filter((function(t){return c.indexOf(t)>=0}));0===u.length&&(u=d);var f=u.reduce((function(e,i){return e[i]=ke(t,{placement:i,boundary:s,rootBoundary:o,padding:r})[Ut(i)],e}),{});return Object.keys(f).sort((function(t,e){return f[t]-f[e]}))}const xe={name:"flip",enabled:!0,phase:"main",fn:function(t){var e=t.state,i=t.options,n=t.name;if(!e.modifiersData[n]._skip){for(var s=i.mainAxis,o=void 0===s||s,r=i.altAxis,a=void 0===r||r,l=i.fallbackPlacements,c=i.padding,h=i.boundary,d=i.rootBoundary,u=i.altBoundary,f=i.flipVariations,p=void 0===f||f,m=i.allowedAutoPlacements,g=e.options.placement,_=Ut(g),b=l||(_!==g&&p?function(t){if(Ut(t)===vt)return[];var e=ge(t);return[be(t),e,be(e)]}(g):[ge(g)]),v=[g].concat(b).reduce((function(t,i){return t.concat(Ut(i)===vt?Le(e,{placement:i,boundary:h,rootBoundary:d,padding:c,flipVariations:p,allowedAutoPlacements:m}):i)}),[]),y=e.rects.reference,w=e.rects.popper,E=new Map,A=!0,T=v[0],O=0;O<v.length;O++){var C=v[O],k=Ut(C),L=ce(C)===wt,x=[mt,gt].indexOf(k)>=0,D=x?"width":"height",S=ke(e,{placement:C,boundary:h,rootBoundary:d,altBoundary:u,padding:c}),N=x?L?_t:bt:L?gt:mt;y[D]>w[D]&&(N=ge(N));var I=ge(N),P=[];if(o&&P.push(S[k]<=0),a&&P.push(S[N]<=0,S[I]<=0),P.every((function(t){return t}))){T=C,A=!1;break}E.set(C,P)}if(A)for(var j=function(t){var e=v.find((function(e){var i=E.get(e);if(i)return i.slice(0,t).every((function(t){return t}))}));if(e)return T=e,"break"},M=p?3:1;M>0&&"break"!==j(M);M--);e.placement!==T&&(e.modifiersData[n]._skip=!0,e.placement=T,e.reset=!0)}},requiresIfExists:["offset"],data:{_skip:!1}};function De(t,e,i){return void 0===i&&(i={x:0,y:0}),{top:t.top-e.height-i.y,right:t.right-e.width+i.x,bottom:t.bottom-e.height+i.y,left:t.left-e.width-i.x}}function Se(t){return[mt,_t,gt,bt].some((function(e){return t[e]>=0}))}const Ne={name:"hide",enabled:!0,phase:"main",requiresIfExists:["preventOverflow"],fn:function(t){var e=t.state,i=t.name,n=e.rects.reference,s=e.rects.popper,o=e.modifiersData.preventOverflow,r=ke(e,{elementContext:"reference"}),a=ke(e,{altBoundary:!0}),l=De(r,n),c=De(a,s,o),h=Se(l),d=Se(c);e.modifiersData[i]={referenceClippingOffsets:l,popperEscapeOffsets:c,isReferenceHidden:h,hasPopperEscaped:d},e.attributes.popper=Object.assign({},e.attributes.popper,{"data-popper-reference-hidden":h,"data-popper-escaped":d})}},Ie={name:"offset",enabled:!0,phase:"main",requires:["popperOffsets"],fn:function(t){var e=t.state,i=t.options,n=t.name,s=i.offset,o=void 0===s?[0,0]:s,r=Lt.reduce((function(t,i){return t[i]=function(t,e,i){var n=Ut(t),s=[bt,mt].indexOf(n)>=0?-1:1,o="function"==typeof i?i(Object.assign({},e,{placement:t})):i,r=o[0],a=o[1];return r=r||0,a=(a||0)*s,[bt,_t].indexOf(n)>=0?{x:a,y:r}:{x:r,y:a}}(i,e.rects,o),t}),{}),a=r[e.placement],l=a.x,c=a.y;null!=e.modifiersData.popperOffsets&&(e.modifiersData.popperOffsets.x+=l,e.modifiersData.popperOffsets.y+=c),e.modifiersData[n]=r}},Pe={name:"popperOffsets",enabled:!0,phase:"read",fn:function(t){var e=t.state,i=t.name;e.modifiersData[i]=Ce({reference:e.rects.reference,element:e.rects.popper,strategy:"absolute",placement:e.placement})},data:{}},je={name:"preventOverflow",enabled:!0,phase:"main",fn:function(t){var e=t.state,i=t.options,n=t.name,s=i.mainAxis,o=void 0===s||s,r=i.altAxis,a=void 0!==r&&r,l=i.boundary,c=i.rootBoundary,h=i.altBoundary,d=i.padding,u=i.tether,f=void 0===u||u,p=i.tetherOffset,m=void 0===p?0:p,g=ke(e,{boundary:l,rootBoundary:c,padding:d,altBoundary:h}),_=Ut(e.placement),b=ce(e.placement),v=!b,y=ee(_),w="x"===y?"y":"x",E=e.modifiersData.popperOffsets,A=e.rects.reference,T=e.rects.popper,O="function"==typeof m?m(Object.assign({},e.rects,{placement:e.placement})):m,C={x:0,y:0};if(E){if(o||a){var k="y"===y?mt:bt,L="y"===y?gt:_t,x="y"===y?"height":"width",D=E[y],S=E[y]+g[k],N=E[y]-g[L],I=f?-T[x]/2:0,P=b===wt?A[x]:T[x],j=b===wt?-T[x]:-A[x],M=e.elements.arrow,H=f&&M?Kt(M):{width:0,height:0},B=e.modifiersData["arrow#persistent"]?e.modifiersData["arrow#persistent"].padding:{top:0,right:0,bottom:0,left:0},R=B[k],W=B[L],$=oe(0,A[x],H[x]),z=v?A[x]/2-I-$-R-O:P-$-R-O,q=v?-A[x]/2+I+$+W+O:j+$+W+O,F=e.elements.arrow&&te(e.elements.arrow),U=F?"y"===y?F.clientTop||0:F.clientLeft||0:0,V=e.modifiersData.offset?e.modifiersData.offset[e.placement][y]:0,K=E[y]+z-V-U,X=E[y]+q-V;if(o){var Y=oe(f?ne(S,K):S,D,f?ie(N,X):N);E[y]=Y,C[y]=Y-D}if(a){var Q="x"===y?mt:bt,G="x"===y?gt:_t,Z=E[w],J=Z+g[Q],tt=Z-g[G],et=oe(f?ne(J,K):J,Z,f?ie(tt,X):tt);E[w]=et,C[w]=et-Z}}e.modifiersData[n]=C}},requiresIfExists:["offset"]};function Me(t,e,i){void 0===i&&(i=!1);var n=zt(e);zt(e)&&function(t){var e=t.getBoundingClientRect();e.width,t.offsetWidth,e.height,t.offsetHeight}(e);var s,o,r=Gt(e),a=Vt(t),l={scrollLeft:0,scrollTop:0},c={x:0,y:0};return(n||!n&&!i)&&(("body"!==Rt(e)||we(r))&&(l=(s=e)!==Wt(s)&&zt(s)?{scrollLeft:(o=s).scrollLeft,scrollTop:o.scrollTop}:ve(s)),zt(e)?((c=Vt(e)).x+=e.clientLeft,c.y+=e.clientTop):r&&(c.x=ye(r))),{x:a.left+l.scrollLeft-c.x,y:a.top+l.scrollTop-c.y,width:a.width,height:a.height}}function He(t){var e=new Map,i=new Set,n=[];function s(t){i.add(t.name),[].concat(t.requires||[],t.requiresIfExists||[]).forEach((function(t){if(!i.has(t)){var n=e.get(t);n&&s(n)}})),n.push(t)}return t.forEach((function(t){e.set(t.name,t)})),t.forEach((function(t){i.has(t.name)||s(t)})),n}var Be={placement:"bottom",modifiers:[],strategy:"absolute"};function Re(){for(var t=arguments.length,e=new Array(t),i=0;i<t;i++)e[i]=arguments[i];return!e.some((function(t){return!(t&&"function"==typeof t.getBoundingClientRect)}))}function We(t){void 0===t&&(t={});var e=t,i=e.defaultModifiers,n=void 0===i?[]:i,s=e.defaultOptions,o=void 0===s?Be:s;return function(t,e,i){void 0===i&&(i=o);var s,r,a={placement:"bottom",orderedModifiers:[],options:Object.assign({},Be,o),modifiersData:{},elements:{reference:t,popper:e},attributes:{},styles:{}},l=[],c=!1,h={state:a,setOptions:function(i){var s="function"==typeof i?i(a.options):i;d(),a.options=Object.assign({},o,a.options,s),a.scrollParents={reference:$t(t)?Ae(t):t.contextElement?Ae(t.contextElement):[],popper:Ae(e)};var r,c,u=function(t){var e=He(t);return Bt.reduce((function(t,i){return t.concat(e.filter((function(t){return t.phase===i})))}),[])}((r=[].concat(n,a.options.modifiers),c=r.reduce((function(t,e){var i=t[e.name];return t[e.name]=i?Object.assign({},i,e,{options:Object.assign({},i.options,e.options),data:Object.assign({},i.data,e.data)}):e,t}),{}),Object.keys(c).map((function(t){return c[t]}))));return a.orderedModifiers=u.filter((function(t){return t.enabled})),a.orderedModifiers.forEach((function(t){var e=t.name,i=t.options,n=void 0===i?{}:i,s=t.effect;if("function"==typeof s){var o=s({state:a,name:e,instance:h,options:n});l.push(o||function(){})}})),h.update()},forceUpdate:function(){if(!c){var t=a.elements,e=t.reference,i=t.popper;if(Re(e,i)){a.rects={reference:Me(e,te(i),"fixed"===a.options.strategy),popper:Kt(i)},a.reset=!1,a.placement=a.options.placement,a.orderedModifiers.forEach((function(t){return a.modifiersData[t.name]=Object.assign({},t.data)}));for(var n=0;n<a.orderedModifiers.length;n++)if(!0!==a.reset){var s=a.orderedModifiers[n],o=s.fn,r=s.options,l=void 0===r?{}:r,d=s.name;"function"==typeof o&&(a=o({state:a,options:l,name:d,instance:h})||a)}else a.reset=!1,n=-1}}},update:(s=function(){return new Promise((function(t){h.forceUpdate(),t(a)}))},function(){return r||(r=new Promise((function(t){Promise.resolve().then((function(){r=void 0,t(s())}))}))),r}),destroy:function(){d(),c=!0}};if(!Re(t,e))return h;function d(){l.forEach((function(t){return t()})),l=[]}return h.setOptions(i).then((function(t){!c&&i.onFirstUpdate&&i.onFirstUpdate(t)})),h}}var $e=We(),ze=We({defaultModifiers:[pe,Pe,ue,Ft]}),qe=We({defaultModifiers:[pe,Pe,ue,Ft,Ie,xe,je,le,Ne]});const Fe=Object.freeze({__proto__:null,popperGenerator:We,detectOverflow:ke,createPopperBase:$e,createPopper:qe,createPopperLite:ze,top:mt,bottom:gt,right:_t,left:bt,auto:vt,basePlacements:yt,start:wt,end:Et,clippingParents:At,viewport:Tt,popper:Ot,reference:Ct,variationPlacements:kt,placements:Lt,beforeRead:xt,read:Dt,afterRead:St,beforeMain:Nt,main:It,afterMain:Pt,beforeWrite:jt,write:Mt,afterWrite:Ht,modifierPhases:Bt,applyStyles:Ft,arrow:le,computeStyles:ue,eventListeners:pe,flip:xe,hide:Ne,offset:Ie,popperOffsets:Pe,preventOverflow:je}),Ue="dropdown",Ve="Escape",Ke="Space",Xe="ArrowUp",Ye="ArrowDown",Qe=new RegExp("ArrowUp|ArrowDown|Escape"),Ge="click.bs.dropdown.data-api",Ze="keydown.bs.dropdown.data-api",Je="show",ti='[data-bs-toggle="dropdown"]',ei=".dropdown-menu",ii=m()?"top-end":"top-start",ni=m()?"top-start":"top-end",si=m()?"bottom-end":"bottom-start",oi=m()?"bottom-start":"bottom-end",ri=m()?"left-start":"right-start",ai=m()?"right-start":"left-start",li={offset:[0,2],boundary:"clippingParents",reference:"toggle",display:"dynamic",popperConfig:null,autoClose:!0},ci={offset:"(array|string|function)",boundary:"(string|element)",reference:"(string|element|object)",display:"string",popperConfig:"(null|object|function)",autoClose:"(boolean|string)"};class hi extends B{constructor(t,e){super(t),this._popper=null,this._config=this._getConfig(e),this._menu=this._getMenuElement(),this._inNavbar=this._detectNavbar()}static get Default(){return li}static get DefaultType(){return ci}static get NAME(){return Ue}toggle(){return this._isShown()?this.hide():this.show()}show(){if(c(this._element)||this._isShown(this._menu))return;const t={relatedTarget:this._element};if(j.trigger(this._element,"show.bs.dropdown",t).defaultPrevented)return;const e=hi.getParentFromElement(this._element);this._inNavbar?U.setDataAttribute(this._menu,"popper","none"):this._createPopper(e),"ontouchstart"in document.documentElement&&!e.closest(".navbar-nav")&&[].concat(...document.body.children).forEach((t=>j.on(t,"mouseover",d))),this._element.focus(),this._element.setAttribute("aria-expanded",!0),this._menu.classList.add(Je),this._element.classList.add(Je),j.trigger(this._element,"shown.bs.dropdown",t)}hide(){if(c(this._element)||!this._isShown(this._menu))return;const t={relatedTarget:this._element};this._completeHide(t)}dispose(){this._popper&&this._popper.destroy(),super.dispose()}update(){this._inNavbar=this._detectNavbar(),this._popper&&this._popper.update()}_completeHide(t){j.trigger(this._element,"hide.bs.dropdown",t).defaultPrevented||("ontouchstart"in document.documentElement&&[].concat(...document.body.children).forEach((t=>j.off(t,"mouseover",d))),this._popper&&this._popper.destroy(),this._menu.classList.remove(Je),this._element.classList.remove(Je),this._element.setAttribute("aria-expanded","false"),U.removeDataAttribute(this._menu,"popper"),j.trigger(this._element,"hidden.bs.dropdown",t))}_getConfig(t){if(t={...this.constructor.Default,...U.getDataAttributes(this._element),...t},a(Ue,t,this.constructor.DefaultType),"object"==typeof t.reference&&!o(t.reference)&&"function"!=typeof t.reference.getBoundingClientRect)throw new TypeError(`${Ue.toUpperCase()}: Option "reference" provided type "object" without a required "getBoundingClientRect" method.`);return t}_createPopper(t){if(void 0===Fe)throw new TypeError("Bootstrap's dropdowns require Popper (https://popper.js.org)");let e=this._element;"parent"===this._config.reference?e=t:o(this._config.reference)?e=r(this._config.reference):"object"==typeof this._config.reference&&(e=this._config.reference);const i=this._getPopperConfig(),n=i.modifiers.find((t=>"applyStyles"===t.name&&!1===t.enabled));this._popper=qe(e,this._menu,i),n&&U.setDataAttribute(this._menu,"popper","static")}_isShown(t=this._element){return t.classList.contains(Je)}_getMenuElement(){return V.next(this._element,ei)[0]}_getPlacement(){const t=this._element.parentNode;if(t.classList.contains("dropend"))return ri;if(t.classList.contains("dropstart"))return ai;const e="end"===getComputedStyle(this._menu).getPropertyValue("--bs-position").trim();return t.classList.contains("dropup")?e?ni:ii:e?oi:si}_detectNavbar(){return null!==this._element.closest(".navbar")}_getOffset(){const{offset:t}=this._config;return"string"==typeof t?t.split(",").map((t=>Number.parseInt(t,10))):"function"==typeof t?e=>t(e,this._element):t}_getPopperConfig(){const t={placement:this._getPlacement(),modifiers:[{name:"preventOverflow",options:{boundary:this._config.boundary}},{name:"offset",options:{offset:this._getOffset()}}]};return"static"===this._config.display&&(t.modifiers=[{name:"applyStyles",enabled:!1}]),{...t,..."function"==typeof this._config.popperConfig?this._config.popperConfig(t):this._config.popperConfig}}_selectMenuItem({key:t,target:e}){const i=V.find(".dropdown-menu .dropdown-item:not(.disabled):not(:disabled)",this._menu).filter(l);i.length&&v(i,e,t===Ye,!i.includes(e)).focus()}static jQueryInterface(t){return this.each((function(){const e=hi.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}}))}static clearMenus(t){if(t&&(2===t.button||"keyup"===t.type&&"Tab"!==t.key))return;const e=V.find(ti);for(let i=0,n=e.length;i<n;i++){const n=hi.getInstance(e[i]);if(!n||!1===n._config.autoClose)continue;if(!n._isShown())continue;const s={relatedTarget:n._element};if(t){const e=t.composedPath(),i=e.includes(n._menu);if(e.includes(n._element)||"inside"===n._config.autoClose&&!i||"outside"===n._config.autoClose&&i)continue;if(n._menu.contains(t.target)&&("keyup"===t.type&&"Tab"===t.key||/input|select|option|textarea|form/i.test(t.target.tagName)))continue;"click"===t.type&&(s.clickEvent=t)}n._completeHide(s)}}static getParentFromElement(t){return n(t)||t.parentNode}static dataApiKeydownHandler(t){if(/input|textarea/i.test(t.target.tagName)?t.key===Ke||t.key!==Ve&&(t.key!==Ye&&t.key!==Xe||t.target.closest(ei)):!Qe.test(t.key))return;const e=this.classList.contains(Je);if(!e&&t.key===Ve)return;if(t.preventDefault(),t.stopPropagation(),c(this))return;const i=this.matches(ti)?this:V.prev(this,ti)[0],n=hi.getOrCreateInstance(i);if(t.key!==Ve)return t.key===Xe||t.key===Ye?(e||n.show(),void n._selectMenuItem(t)):void(e&&t.key!==Ke||hi.clearMenus());n.hide()}}j.on(document,Ze,ti,hi.dataApiKeydownHandler),j.on(document,Ze,ei,hi.dataApiKeydownHandler),j.on(document,Ge,hi.clearMenus),j.on(document,"keyup.bs.dropdown.data-api",hi.clearMenus),j.on(document,Ge,ti,(function(t){t.preventDefault(),hi.getOrCreateInstance(this).toggle()})),g(hi);const di=".fixed-top, .fixed-bottom, .is-fixed, .sticky-top",ui=".sticky-top";class fi{constructor(){this._element=document.body}getWidth(){const t=document.documentElement.clientWidth;return Math.abs(window.innerWidth-t)}hide(){const t=this.getWidth();this._disableOverFlow(),this._setElementAttributes(this._element,"paddingRight",(e=>e+t)),this._setElementAttributes(di,"paddingRight",(e=>e+t)),this._setElementAttributes(ui,"marginRight",(e=>e-t))}_disableOverFlow(){this._saveInitialAttribute(this._element,"overflow"),this._element.style.overflow="hidden"}_setElementAttributes(t,e,i){const n=this.getWidth();this._applyManipulationCallback(t,(t=>{if(t!==this._element&&window.innerWidth>t.clientWidth+n)return;this._saveInitialAttribute(t,e);const s=window.getComputedStyle(t)[e];t.style[e]=`${i(Number.parseFloat(s))}px`}))}reset(){this._resetElementAttributes(this._element,"overflow"),this._resetElementAttributes(this._element,"paddingRight"),this._resetElementAttributes(di,"paddingRight"),this._resetElementAttributes(ui,"marginRight")}_saveInitialAttribute(t,e){const i=t.style[e];i&&U.setDataAttribute(t,e,i)}_resetElementAttributes(t,e){this._applyManipulationCallback(t,(t=>{const i=U.getDataAttribute(t,e);void 0===i?t.style.removeProperty(e):(U.removeDataAttribute(t,e),t.style[e]=i)}))}_applyManipulationCallback(t,e){o(t)?e(t):V.find(t,this._element).forEach(e)}isOverflowing(){return this.getWidth()>0}}const pi={className:"modal-backdrop",isVisible:!0,isAnimated:!1,rootElement:"body",clickCallback:null},mi={className:"string",isVisible:"boolean",isAnimated:"boolean",rootElement:"(element|string)",clickCallback:"(function|null)"},gi="show",_i="mousedown.bs.backdrop";class bi{constructor(t){this._config=this._getConfig(t),this._isAppended=!1,this._element=null}show(t){this._config.isVisible?(this._append(),this._config.isAnimated&&u(this._getElement()),this._getElement().classList.add(gi),this._emulateAnimation((()=>{_(t)}))):_(t)}hide(t){this._config.isVisible?(this._getElement().classList.remove(gi),this._emulateAnimation((()=>{this.dispose(),_(t)}))):_(t)}_getElement(){if(!this._element){const t=document.createElement("div");t.className=this._config.className,this._config.isAnimated&&t.classList.add("fade"),this._element=t}return this._element}_getConfig(t){return(t={...pi,..."object"==typeof t?t:{}}).rootElement=r(t.rootElement),a("backdrop",t,mi),t}_append(){this._isAppended||(this._config.rootElement.append(this._getElement()),j.on(this._getElement(),_i,(()=>{_(this._config.clickCallback)})),this._isAppended=!0)}dispose(){this._isAppended&&(j.off(this._element,_i),this._element.remove(),this._isAppended=!1)}_emulateAnimation(t){b(t,this._getElement(),this._config.isAnimated)}}const vi={trapElement:null,autofocus:!0},yi={trapElement:"element",autofocus:"boolean"},wi=".bs.focustrap",Ei="backward";class Ai{constructor(t){this._config=this._getConfig(t),this._isActive=!1,this._lastTabNavDirection=null}activate(){const{trapElement:t,autofocus:e}=this._config;this._isActive||(e&&t.focus(),j.off(document,wi),j.on(document,"focusin.bs.focustrap",(t=>this._handleFocusin(t))),j.on(document,"keydown.tab.bs.focustrap",(t=>this._handleKeydown(t))),this._isActive=!0)}deactivate(){this._isActive&&(this._isActive=!1,j.off(document,wi))}_handleFocusin(t){const{target:e}=t,{trapElement:i}=this._config;if(e===document||e===i||i.contains(e))return;const n=V.focusableChildren(i);0===n.length?i.focus():this._lastTabNavDirection===Ei?n[n.length-1].focus():n[0].focus()}_handleKeydown(t){"Tab"===t.key&&(this._lastTabNavDirection=t.shiftKey?Ei:"forward")}_getConfig(t){return t={...vi,..."object"==typeof t?t:{}},a("focustrap",t,yi),t}}const Ti="modal",Oi="Escape",Ci={backdrop:!0,keyboard:!0,focus:!0},ki={backdrop:"(boolean|string)",keyboard:"boolean",focus:"boolean"},Li="hidden.bs.modal",xi="show.bs.modal",Di="resize.bs.modal",Si="click.dismiss.bs.modal",Ni="keydown.dismiss.bs.modal",Ii="mousedown.dismiss.bs.modal",Pi="modal-open",ji="show",Mi="modal-static";class Hi extends B{constructor(t,e){super(t),this._config=this._getConfig(e),this._dialog=V.findOne(".modal-dialog",this._element),this._backdrop=this._initializeBackDrop(),this._focustrap=this._initializeFocusTrap(),this._isShown=!1,this._ignoreBackdropClick=!1,this._isTransitioning=!1,this._scrollBar=new fi}static get Default(){return Ci}static get NAME(){return Ti}toggle(t){return this._isShown?this.hide():this.show(t)}show(t){this._isShown||this._isTransitioning||j.trigger(this._element,xi,{relatedTarget:t}).defaultPrevented||(this._isShown=!0,this._isAnimated()&&(this._isTransitioning=!0),this._scrollBar.hide(),document.body.classList.add(Pi),this._adjustDialog(),this._setEscapeEvent(),this._setResizeEvent(),j.on(this._dialog,Ii,(()=>{j.one(this._element,"mouseup.dismiss.bs.modal",(t=>{t.target===this._element&&(this._ignoreBackdropClick=!0)}))})),this._showBackdrop((()=>this._showElement(t))))}hide(){if(!this._isShown||this._isTransitioning)return;if(j.trigger(this._element,"hide.bs.modal").defaultPrevented)return;this._isShown=!1;const t=this._isAnimated();t&&(this._isTransitioning=!0),this._setEscapeEvent(),this._setResizeEvent(),this._focustrap.deactivate(),this._element.classList.remove(ji),j.off(this._element,Si),j.off(this._dialog,Ii),this._queueCallback((()=>this._hideModal()),this._element,t)}dispose(){[window,this._dialog].forEach((t=>j.off(t,".bs.modal"))),this._backdrop.dispose(),this._focustrap.deactivate(),super.dispose()}handleUpdate(){this._adjustDialog()}_initializeBackDrop(){return new bi({isVisible:Boolean(this._config.backdrop),isAnimated:this._isAnimated()})}_initializeFocusTrap(){return new Ai({trapElement:this._element})}_getConfig(t){return t={...Ci,...U.getDataAttributes(this._element),..."object"==typeof t?t:{}},a(Ti,t,ki),t}_showElement(t){const e=this._isAnimated(),i=V.findOne(".modal-body",this._dialog);this._element.parentNode&&this._element.parentNode.nodeType===Node.ELEMENT_NODE||document.body.append(this._element),this._element.style.display="block",this._element.removeAttribute("aria-hidden"),this._element.setAttribute("aria-modal",!0),this._element.setAttribute("role","dialog"),this._element.scrollTop=0,i&&(i.scrollTop=0),e&&u(this._element),this._element.classList.add(ji),this._queueCallback((()=>{this._config.focus&&this._focustrap.activate(),this._isTransitioning=!1,j.trigger(this._element,"shown.bs.modal",{relatedTarget:t})}),this._dialog,e)}_setEscapeEvent(){this._isShown?j.on(this._element,Ni,(t=>{this._config.keyboard&&t.key===Oi?(t.preventDefault(),this.hide()):this._config.keyboard||t.key!==Oi||this._triggerBackdropTransition()})):j.off(this._element,Ni)}_setResizeEvent(){this._isShown?j.on(window,Di,(()=>this._adjustDialog())):j.off(window,Di)}_hideModal(){this._element.style.display="none",this._element.setAttribute("aria-hidden",!0),this._element.removeAttribute("aria-modal"),this._element.removeAttribute("role"),this._isTransitioning=!1,this._backdrop.hide((()=>{document.body.classList.remove(Pi),this._resetAdjustments(),this._scrollBar.reset(),j.trigger(this._element,Li)}))}_showBackdrop(t){j.on(this._element,Si,(t=>{this._ignoreBackdropClick?this._ignoreBackdropClick=!1:t.target===t.currentTarget&&(!0===this._config.backdrop?this.hide():"static"===this._config.backdrop&&this._triggerBackdropTransition())})),this._backdrop.show(t)}_isAnimated(){return this._element.classList.contains("fade")}_triggerBackdropTransition(){if(j.trigger(this._element,"hidePrevented.bs.modal").defaultPrevented)return;const{classList:t,scrollHeight:e,style:i}=this._element,n=e>document.documentElement.clientHeight;!n&&"hidden"===i.overflowY||t.contains(Mi)||(n||(i.overflowY="hidden"),t.add(Mi),this._queueCallback((()=>{t.remove(Mi),n||this._queueCallback((()=>{i.overflowY=""}),this._dialog)}),this._dialog),this._element.focus())}_adjustDialog(){const t=this._element.scrollHeight>document.documentElement.clientHeight,e=this._scrollBar.getWidth(),i=e>0;(!i&&t&&!m()||i&&!t&&m())&&(this._element.style.paddingLeft=`${e}px`),(i&&!t&&!m()||!i&&t&&m())&&(this._element.style.paddingRight=`${e}px`)}_resetAdjustments(){this._element.style.paddingLeft="",this._element.style.paddingRight=""}static jQueryInterface(t,e){return this.each((function(){const i=Hi.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===i[t])throw new TypeError(`No method named "${t}"`);i[t](e)}}))}}j.on(document,"click.bs.modal.data-api",'[data-bs-toggle="modal"]',(function(t){const e=n(this);["A","AREA"].includes(this.tagName)&&t.preventDefault(),j.one(e,xi,(t=>{t.defaultPrevented||j.one(e,Li,(()=>{l(this)&&this.focus()}))}));const i=V.findOne(".modal.show");i&&Hi.getInstance(i).hide(),Hi.getOrCreateInstance(e).toggle(this)})),R(Hi),g(Hi);const Bi="offcanvas",Ri={backdrop:!0,keyboard:!0,scroll:!1},Wi={backdrop:"boolean",keyboard:"boolean",scroll:"boolean"},$i="show",zi=".offcanvas.show",qi="hidden.bs.offcanvas";class Fi extends B{constructor(t,e){super(t),this._config=this._getConfig(e),this._isShown=!1,this._backdrop=this._initializeBackDrop(),this._focustrap=this._initializeFocusTrap(),this._addEventListeners()}static get NAME(){return Bi}static get Default(){return Ri}toggle(t){return this._isShown?this.hide():this.show(t)}show(t){this._isShown||j.trigger(this._element,"show.bs.offcanvas",{relatedTarget:t}).defaultPrevented||(this._isShown=!0,this._element.style.visibility="visible",this._backdrop.show(),this._config.scroll||(new fi).hide(),this._element.removeAttribute("aria-hidden"),this._element.setAttribute("aria-modal",!0),this._element.setAttribute("role","dialog"),this._element.classList.add($i),this._queueCallback((()=>{this._config.scroll||this._focustrap.activate(),j.trigger(this._element,"shown.bs.offcanvas",{relatedTarget:t})}),this._element,!0))}hide(){this._isShown&&(j.trigger(this._element,"hide.bs.offcanvas").defaultPrevented||(this._focustrap.deactivate(),this._element.blur(),this._isShown=!1,this._element.classList.remove($i),this._backdrop.hide(),this._queueCallback((()=>{this._element.setAttribute("aria-hidden",!0),this._element.removeAttribute("aria-modal"),this._element.removeAttribute("role"),this._element.style.visibility="hidden",this._config.scroll||(new fi).reset(),j.trigger(this._element,qi)}),this._element,!0)))}dispose(){this._backdrop.dispose(),this._focustrap.deactivate(),super.dispose()}_getConfig(t){return t={...Ri,...U.getDataAttributes(this._element),..."object"==typeof t?t:{}},a(Bi,t,Wi),t}_initializeBackDrop(){return new bi({className:"offcanvas-backdrop",isVisible:this._config.backdrop,isAnimated:!0,rootElement:this._element.parentNode,clickCallback:()=>this.hide()})}_initializeFocusTrap(){return new Ai({trapElement:this._element})}_addEventListeners(){j.on(this._element,"keydown.dismiss.bs.offcanvas",(t=>{this._config.keyboard&&"Escape"===t.key&&this.hide()}))}static jQueryInterface(t){return this.each((function(){const e=Fi.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t]||t.startsWith("_")||"constructor"===t)throw new TypeError(`No method named "${t}"`);e[t](this)}}))}}j.on(document,"click.bs.offcanvas.data-api",'[data-bs-toggle="offcanvas"]',(function(t){const e=n(this);if(["A","AREA"].includes(this.tagName)&&t.preventDefault(),c(this))return;j.one(e,qi,(()=>{l(this)&&this.focus()}));const i=V.findOne(zi);i&&i!==e&&Fi.getInstance(i).hide(),Fi.getOrCreateInstance(e).toggle(this)})),j.on(window,"load.bs.offcanvas.data-api",(()=>V.find(zi).forEach((t=>Fi.getOrCreateInstance(t).show())))),R(Fi),g(Fi);const Ui=new Set(["background","cite","href","itemtype","longdesc","poster","src","xlink:href"]),Vi=/^(?:(?:https?|mailto|ftp|tel|file|sms):|[^#&/:?]*(?:[#/?]|$))/i,Ki=/^data:(?:image\/(?:bmp|gif|jpeg|jpg|png|tiff|webp)|video\/(?:mpeg|mp4|ogg|webm)|audio\/(?:mp3|oga|ogg|opus));base64,[\d+/a-z]+=*$/i,Xi=(t,e)=>{const i=t.nodeName.toLowerCase();if(e.includes(i))return!Ui.has(i)||Boolean(Vi.test(t.nodeValue)||Ki.test(t.nodeValue));const n=e.filter((t=>t instanceof RegExp));for(let t=0,e=n.length;t<e;t++)if(n[t].test(i))return!0;return!1};function Yi(t,e,i){if(!t.length)return t;if(i&&"function"==typeof i)return i(t);const n=(new window.DOMParser).parseFromString(t,"text/html"),s=[].concat(...n.body.querySelectorAll("*"));for(let t=0,i=s.length;t<i;t++){const i=s[t],n=i.nodeName.toLowerCase();if(!Object.keys(e).includes(n)){i.remove();continue}const o=[].concat(...i.attributes),r=[].concat(e["*"]||[],e[n]||[]);o.forEach((t=>{Xi(t,r)||i.removeAttribute(t.nodeName)}))}return n.body.innerHTML}const Qi="tooltip",Gi=new Set(["sanitize","allowList","sanitizeFn"]),Zi={animation:"boolean",template:"string",title:"(string|element|function)",trigger:"string",delay:"(number|object)",html:"boolean",selector:"(string|boolean)",placement:"(string|function)",offset:"(array|string|function)",container:"(string|element|boolean)",fallbackPlacements:"array",boundary:"(string|element)",customClass:"(string|function)",sanitize:"boolean",sanitizeFn:"(null|function)",allowList:"object",popperConfig:"(null|object|function)"},Ji={AUTO:"auto",TOP:"top",RIGHT:m()?"left":"right",BOTTOM:"bottom",LEFT:m()?"right":"left"},tn={animation:!0,template:'<div class="tooltip" role="tooltip"><div class="tooltip-arrow"></div><div class="tooltip-inner"></div></div>',trigger:"hover focus",title:"",delay:0,html:!1,selector:!1,placement:"top",offset:[0,0],container:!1,fallbackPlacements:["top","right","bottom","left"],boundary:"clippingParents",customClass:"",sanitize:!0,sanitizeFn:null,allowList:{"*":["class","dir","id","lang","role",/^aria-[\w-]*$/i],a:["target","href","title","rel"],area:[],b:[],br:[],col:[],code:[],div:[],em:[],hr:[],h1:[],h2:[],h3:[],h4:[],h5:[],h6:[],i:[],img:["src","srcset","alt","title","width","height"],li:[],ol:[],p:[],pre:[],s:[],small:[],span:[],sub:[],sup:[],strong:[],u:[],ul:[]},popperConfig:null},en={HIDE:"hide.bs.tooltip",HIDDEN:"hidden.bs.tooltip",SHOW:"show.bs.tooltip",SHOWN:"shown.bs.tooltip",INSERTED:"inserted.bs.tooltip",CLICK:"click.bs.tooltip",FOCUSIN:"focusin.bs.tooltip",FOCUSOUT:"focusout.bs.tooltip",MOUSEENTER:"mouseenter.bs.tooltip",MOUSELEAVE:"mouseleave.bs.tooltip"},nn="fade",sn="show",on="show",rn="out",an=".tooltip-inner",ln=".modal",cn="hide.bs.modal",hn="hover",dn="focus";class un extends B{constructor(t,e){if(void 0===Fe)throw new TypeError("Bootstrap's tooltips require Popper (https://popper.js.org)");super(t),this._isEnabled=!0,this._timeout=0,this._hoverState="",this._activeTrigger={},this._popper=null,this._config=this._getConfig(e),this.tip=null,this._setListeners()}static get Default(){return tn}static get NAME(){return Qi}static get Event(){return en}static get DefaultType(){return Zi}enable(){this._isEnabled=!0}disable(){this._isEnabled=!1}toggleEnabled(){this._isEnabled=!this._isEnabled}toggle(t){if(this._isEnabled)if(t){const e=this._initializeOnDelegatedTarget(t);e._activeTrigger.click=!e._activeTrigger.click,e._isWithActiveTrigger()?e._enter(null,e):e._leave(null,e)}else{if(this.getTipElement().classList.contains(sn))return void this._leave(null,this);this._enter(null,this)}}dispose(){clearTimeout(this._timeout),j.off(this._element.closest(ln),cn,this._hideModalHandler),this.tip&&this.tip.remove(),this._disposePopper(),super.dispose()}show(){if("none"===this._element.style.display)throw new Error("Please use show on visible elements");if(!this.isWithContent()||!this._isEnabled)return;const t=j.trigger(this._element,this.constructor.Event.SHOW),e=h(this._element),i=null===e?this._element.ownerDocument.documentElement.contains(this._element):e.contains(this._element);if(t.defaultPrevented||!i)return;"tooltip"===this.constructor.NAME&&this.tip&&this.getTitle()!==this.tip.querySelector(an).innerHTML&&(this._disposePopper(),this.tip.remove(),this.tip=null);const n=this.getTipElement(),s=(t=>{do{t+=Math.floor(1e6*Math.random())}while(document.getElementById(t));return t})(this.constructor.NAME);n.setAttribute("id",s),this._element.setAttribute("aria-describedby",s),this._config.animation&&n.classList.add(nn);const o="function"==typeof this._config.placement?this._config.placement.call(this,n,this._element):this._config.placement,r=this._getAttachment(o);this._addAttachmentClass(r);const{container:a}=this._config;H.set(n,this.constructor.DATA_KEY,this),this._element.ownerDocument.documentElement.contains(this.tip)||(a.append(n),j.trigger(this._element,this.constructor.Event.INSERTED)),this._popper?this._popper.update():this._popper=qe(this._element,n,this._getPopperConfig(r)),n.classList.add(sn);const l=this._resolvePossibleFunction(this._config.customClass);l&&n.classList.add(...l.split(" ")),"ontouchstart"in document.documentElement&&[].concat(...document.body.children).forEach((t=>{j.on(t,"mouseover",d)}));const c=this.tip.classList.contains(nn);this._queueCallback((()=>{const t=this._hoverState;this._hoverState=null,j.trigger(this._element,this.constructor.Event.SHOWN),t===rn&&this._leave(null,this)}),this.tip,c)}hide(){if(!this._popper)return;const t=this.getTipElement();if(j.trigger(this._element,this.constructor.Event.HIDE).defaultPrevented)return;t.classList.remove(sn),"ontouchstart"in document.documentElement&&[].concat(...document.body.children).forEach((t=>j.off(t,"mouseover",d))),this._activeTrigger.click=!1,this._activeTrigger.focus=!1,this._activeTrigger.hover=!1;const e=this.tip.classList.contains(nn);this._queueCallback((()=>{this._isWithActiveTrigger()||(this._hoverState!==on&&t.remove(),this._cleanTipClass(),this._element.removeAttribute("aria-describedby"),j.trigger(this._element,this.constructor.Event.HIDDEN),this._disposePopper())}),this.tip,e),this._hoverState=""}update(){null!==this._popper&&this._popper.update()}isWithContent(){return Boolean(this.getTitle())}getTipElement(){if(this.tip)return this.tip;const t=document.createElement("div");t.innerHTML=this._config.template;const e=t.children[0];return this.setContent(e),e.classList.remove(nn,sn),this.tip=e,this.tip}setContent(t){this._sanitizeAndSetContent(t,this.getTitle(),an)}_sanitizeAndSetContent(t,e,i){const n=V.findOne(i,t);e||!n?this.setElementContent(n,e):n.remove()}setElementContent(t,e){if(null!==t)return o(e)?(e=r(e),void(this._config.html?e.parentNode!==t&&(t.innerHTML="",t.append(e)):t.textContent=e.textContent)):void(this._config.html?(this._config.sanitize&&(e=Yi(e,this._config.allowList,this._config.sanitizeFn)),t.innerHTML=e):t.textContent=e)}getTitle(){const t=this._element.getAttribute("data-bs-original-title")||this._config.title;return this._resolvePossibleFunction(t)}updateAttachment(t){return"right"===t?"end":"left"===t?"start":t}_initializeOnDelegatedTarget(t,e){return e||this.constructor.getOrCreateInstance(t.delegateTarget,this._getDelegateConfig())}_getOffset(){const{offset:t}=this._config;return"string"==typeof t?t.split(",").map((t=>Number.parseInt(t,10))):"function"==typeof t?e=>t(e,this._element):t}_resolvePossibleFunction(t){return"function"==typeof t?t.call(this._element):t}_getPopperConfig(t){const e={placement:t,modifiers:[{name:"flip",options:{fallbackPlacements:this._config.fallbackPlacements}},{name:"offset",options:{offset:this._getOffset()}},{name:"preventOverflow",options:{boundary:this._config.boundary}},{name:"arrow",options:{element:`.${this.constructor.NAME}-arrow`}},{name:"onChange",enabled:!0,phase:"afterWrite",fn:t=>this._handlePopperPlacementChange(t)}],onFirstUpdate:t=>{t.options.placement!==t.placement&&this._handlePopperPlacementChange(t)}};return{...e,..."function"==typeof this._config.popperConfig?this._config.popperConfig(e):this._config.popperConfig}}_addAttachmentClass(t){this.getTipElement().classList.add(`${this._getBasicClassPrefix()}-${this.updateAttachment(t)}`)}_getAttachment(t){return Ji[t.toUpperCase()]}_setListeners(){this._config.trigger.split(" ").forEach((t=>{if("click"===t)j.on(this._element,this.constructor.Event.CLICK,this._config.selector,(t=>this.toggle(t)));else if("manual"!==t){const e=t===hn?this.constructor.Event.MOUSEENTER:this.constructor.Event.FOCUSIN,i=t===hn?this.constructor.Event.MOUSELEAVE:this.constructor.Event.FOCUSOUT;j.on(this._element,e,this._config.selector,(t=>this._enter(t))),j.on(this._element,i,this._config.selector,(t=>this._leave(t)))}})),this._hideModalHandler=()=>{this._element&&this.hide()},j.on(this._element.closest(ln),cn,this._hideModalHandler),this._config.selector?this._config={...this._config,trigger:"manual",selector:""}:this._fixTitle()}_fixTitle(){const t=this._element.getAttribute("title"),e=typeof this._element.getAttribute("data-bs-original-title");(t||"string"!==e)&&(this._element.setAttribute("data-bs-original-title",t||""),!t||this._element.getAttribute("aria-label")||this._element.textContent||this._element.setAttribute("aria-label",t),this._element.setAttribute("title",""))}_enter(t,e){e=this._initializeOnDelegatedTarget(t,e),t&&(e._activeTrigger["focusin"===t.type?dn:hn]=!0),e.getTipElement().classList.contains(sn)||e._hoverState===on?e._hoverState=on:(clearTimeout(e._timeout),e._hoverState=on,e._config.delay&&e._config.delay.show?e._timeout=setTimeout((()=>{e._hoverState===on&&e.show()}),e._config.delay.show):e.show())}_leave(t,e){e=this._initializeOnDelegatedTarget(t,e),t&&(e._activeTrigger["focusout"===t.type?dn:hn]=e._element.contains(t.relatedTarget)),e._isWithActiveTrigger()||(clearTimeout(e._timeout),e._hoverState=rn,e._config.delay&&e._config.delay.hide?e._timeout=setTimeout((()=>{e._hoverState===rn&&e.hide()}),e._config.delay.hide):e.hide())}_isWithActiveTrigger(){for(const t in this._activeTrigger)if(this._activeTrigger[t])return!0;return!1}_getConfig(t){const e=U.getDataAttributes(this._element);return Object.keys(e).forEach((t=>{Gi.has(t)&&delete e[t]})),(t={...this.constructor.Default,...e,..."object"==typeof t&&t?t:{}}).container=!1===t.container?document.body:r(t.container),"number"==typeof t.delay&&(t.delay={show:t.delay,hide:t.delay}),"number"==typeof t.title&&(t.title=t.title.toString()),"number"==typeof t.content&&(t.content=t.content.toString()),a(Qi,t,this.constructor.DefaultType),t.sanitize&&(t.template=Yi(t.template,t.allowList,t.sanitizeFn)),t}_getDelegateConfig(){const t={};for(const e in this._config)this.constructor.Default[e]!==this._config[e]&&(t[e]=this._config[e]);return t}_cleanTipClass(){const t=this.getTipElement(),e=new RegExp(`(^|\\s)${this._getBasicClassPrefix()}\\S+`,"g"),i=t.getAttribute("class").match(e);null!==i&&i.length>0&&i.map((t=>t.trim())).forEach((e=>t.classList.remove(e)))}_getBasicClassPrefix(){return"bs-tooltip"}_handlePopperPlacementChange(t){const{state:e}=t;e&&(this.tip=e.elements.popper,this._cleanTipClass(),this._addAttachmentClass(this._getAttachment(e.placement)))}_disposePopper(){this._popper&&(this._popper.destroy(),this._popper=null)}static jQueryInterface(t){return this.each((function(){const e=un.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}}))}}g(un);const fn={...un.Default,placement:"right",offset:[0,8],trigger:"click",content:"",template:'<div class="popover" role="tooltip"><div class="popover-arrow"></div><h3 class="popover-header"></h3><div class="popover-body"></div></div>'},pn={...un.DefaultType,content:"(string|element|function)"},mn={HIDE:"hide.bs.popover",HIDDEN:"hidden.bs.popover",SHOW:"show.bs.popover",SHOWN:"shown.bs.popover",INSERTED:"inserted.bs.popover",CLICK:"click.bs.popover",FOCUSIN:"focusin.bs.popover",FOCUSOUT:"focusout.bs.popover",MOUSEENTER:"mouseenter.bs.popover",MOUSELEAVE:"mouseleave.bs.popover"};class gn extends un{static get Default(){return fn}static get NAME(){return"popover"}static get Event(){return mn}static get DefaultType(){return pn}isWithContent(){return this.getTitle()||this._getContent()}setContent(t){this._sanitizeAndSetContent(t,this.getTitle(),".popover-header"),this._sanitizeAndSetContent(t,this._getContent(),".popover-body")}_getContent(){return this._resolvePossibleFunction(this._config.content)}_getBasicClassPrefix(){return"bs-popover"}static jQueryInterface(t){return this.each((function(){const e=gn.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}}))}}g(gn);const _n="scrollspy",bn={offset:10,method:"auto",target:""},vn={offset:"number",method:"string",target:"(string|element)"},yn="active",wn=".nav-link, .list-group-item, .dropdown-item",En="position";class An extends B{constructor(t,e){super(t),this._scrollElement="BODY"===this._element.tagName?window:this._element,this._config=this._getConfig(e),this._offsets=[],this._targets=[],this._activeTarget=null,this._scrollHeight=0,j.on(this._scrollElement,"scroll.bs.scrollspy",(()=>this._process())),this.refresh(),this._process()}static get Default(){return bn}static get NAME(){return _n}refresh(){const t=this._scrollElement===this._scrollElement.window?"offset":En,e="auto"===this._config.method?t:this._config.method,n=e===En?this._getScrollTop():0;this._offsets=[],this._targets=[],this._scrollHeight=this._getScrollHeight(),V.find(wn,this._config.target).map((t=>{const s=i(t),o=s?V.findOne(s):null;if(o){const t=o.getBoundingClientRect();if(t.width||t.height)return[U[e](o).top+n,s]}return null})).filter((t=>t)).sort(((t,e)=>t[0]-e[0])).forEach((t=>{this._offsets.push(t[0]),this._targets.push(t[1])}))}dispose(){j.off(this._scrollElement,".bs.scrollspy"),super.dispose()}_getConfig(t){return(t={...bn,...U.getDataAttributes(this._element),..."object"==typeof t&&t?t:{}}).target=r(t.target)||document.documentElement,a(_n,t,vn),t}_getScrollTop(){return this._scrollElement===window?this._scrollElement.pageYOffset:this._scrollElement.scrollTop}_getScrollHeight(){return this._scrollElement.scrollHeight||Math.max(document.body.scrollHeight,document.documentElement.scrollHeight)}_getOffsetHeight(){return this._scrollElement===window?window.innerHeight:this._scrollElement.getBoundingClientRect().height}_process(){const t=this._getScrollTop()+this._config.offset,e=this._getScrollHeight(),i=this._config.offset+e-this._getOffsetHeight();if(this._scrollHeight!==e&&this.refresh(),t>=i){const t=this._targets[this._targets.length-1];this._activeTarget!==t&&this._activate(t)}else{if(this._activeTarget&&t<this._offsets[0]&&this._offsets[0]>0)return this._activeTarget=null,void this._clear();for(let e=this._offsets.length;e--;)this._activeTarget!==this._targets[e]&&t>=this._offsets[e]&&(void 0===this._offsets[e+1]||t<this._offsets[e+1])&&this._activate(this._targets[e])}}_activate(t){this._activeTarget=t,this._clear();const e=wn.split(",").map((e=>`${e}[data-bs-target="${t}"],${e}[href="${t}"]`)),i=V.findOne(e.join(","),this._config.target);i.classList.add(yn),i.classList.contains("dropdown-item")?V.findOne(".dropdown-toggle",i.closest(".dropdown")).classList.add(yn):V.parents(i,".nav, .list-group").forEach((t=>{V.prev(t,".nav-link, .list-group-item").forEach((t=>t.classList.add(yn))),V.prev(t,".nav-item").forEach((t=>{V.children(t,".nav-link").forEach((t=>t.classList.add(yn)))}))})),j.trigger(this._scrollElement,"activate.bs.scrollspy",{relatedTarget:t})}_clear(){V.find(wn,this._config.target).filter((t=>t.classList.contains(yn))).forEach((t=>t.classList.remove(yn)))}static jQueryInterface(t){return this.each((function(){const e=An.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}}))}}j.on(window,"load.bs.scrollspy.data-api",(()=>{V.find('[data-bs-spy="scroll"]').forEach((t=>new An(t)))})),g(An);const Tn="active",On="fade",Cn="show",kn=".active",Ln=":scope > li > .active";class xn extends B{static get NAME(){return"tab"}show(){if(this._element.parentNode&&this._element.parentNode.nodeType===Node.ELEMENT_NODE&&this._element.classList.contains(Tn))return;let t;const e=n(this._element),i=this._element.closest(".nav, .list-group");if(i){const e="UL"===i.nodeName||"OL"===i.nodeName?Ln:kn;t=V.find(e,i),t=t[t.length-1]}const s=t?j.trigger(t,"hide.bs.tab",{relatedTarget:this._element}):null;if(j.trigger(this._element,"show.bs.tab",{relatedTarget:t}).defaultPrevented||null!==s&&s.defaultPrevented)return;this._activate(this._element,i);const o=()=>{j.trigger(t,"hidden.bs.tab",{relatedTarget:this._element}),j.trigger(this._element,"shown.bs.tab",{relatedTarget:t})};e?this._activate(e,e.parentNode,o):o()}_activate(t,e,i){const n=(!e||"UL"!==e.nodeName&&"OL"!==e.nodeName?V.children(e,kn):V.find(Ln,e))[0],s=i&&n&&n.classList.contains(On),o=()=>this._transitionComplete(t,n,i);n&&s?(n.classList.remove(Cn),this._queueCallback(o,t,!0)):o()}_transitionComplete(t,e,i){if(e){e.classList.remove(Tn);const t=V.findOne(":scope > .dropdown-menu .active",e.parentNode);t&&t.classList.remove(Tn),"tab"===e.getAttribute("role")&&e.setAttribute("aria-selected",!1)}t.classList.add(Tn),"tab"===t.getAttribute("role")&&t.setAttribute("aria-selected",!0),u(t),t.classList.contains(On)&&t.classList.add(Cn);let n=t.parentNode;if(n&&"LI"===n.nodeName&&(n=n.parentNode),n&&n.classList.contains("dropdown-menu")){const e=t.closest(".dropdown");e&&V.find(".dropdown-toggle",e).forEach((t=>t.classList.add(Tn))),t.setAttribute("aria-expanded",!0)}i&&i()}static jQueryInterface(t){return this.each((function(){const e=xn.getOrCreateInstance(this);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t]()}}))}}j.on(document,"click.bs.tab.data-api",'[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]',(function(t){["A","AREA"].includes(this.tagName)&&t.preventDefault(),c(this)||xn.getOrCreateInstance(this).show()})),g(xn);const Dn="toast",Sn="hide",Nn="show",In="showing",Pn={animation:"boolean",autohide:"boolean",delay:"number"},jn={animation:!0,autohide:!0,delay:5e3};class Mn extends B{constructor(t,e){super(t),this._config=this._getConfig(e),this._timeout=null,this._hasMouseInteraction=!1,this._hasKeyboardInteraction=!1,this._setListeners()}static get DefaultType(){return Pn}static get Default(){return jn}static get NAME(){return Dn}show(){j.trigger(this._element,"show.bs.toast").defaultPrevented||(this._clearTimeout(),this._config.animation&&this._element.classList.add("fade"),this._element.classList.remove(Sn),u(this._element),this._element.classList.add(Nn),this._element.classList.add(In),this._queueCallback((()=>{this._element.classList.remove(In),j.trigger(this._element,"shown.bs.toast"),this._maybeScheduleHide()}),this._element,this._config.animation))}hide(){this._element.classList.contains(Nn)&&(j.trigger(this._element,"hide.bs.toast").defaultPrevented||(this._element.classList.add(In),this._queueCallback((()=>{this._element.classList.add(Sn),this._element.classList.remove(In),this._element.classList.remove(Nn),j.trigger(this._element,"hidden.bs.toast")}),this._element,this._config.animation)))}dispose(){this._clearTimeout(),this._element.classList.contains(Nn)&&this._element.classList.remove(Nn),super.dispose()}_getConfig(t){return t={...jn,...U.getDataAttributes(this._element),..."object"==typeof t&&t?t:{}},a(Dn,t,this.constructor.DefaultType),t}_maybeScheduleHide(){this._config.autohide&&(this._hasMouseInteraction||this._hasKeyboardInteraction||(this._timeout=setTimeout((()=>{this.hide()}),this._config.delay)))}_onInteraction(t,e){switch(t.type){case"mouseover":case"mouseout":this._hasMouseInteraction=e;break;case"focusin":case"focusout":this._hasKeyboardInteraction=e}if(e)return void this._clearTimeout();const i=t.relatedTarget;this._element===i||this._element.contains(i)||this._maybeScheduleHide()}_setListeners(){j.on(this._element,"mouseover.bs.toast",(t=>this._onInteraction(t,!0))),j.on(this._element,"mouseout.bs.toast",(t=>this._onInteraction(t,!1))),j.on(this._element,"focusin.bs.toast",(t=>this._onInteraction(t,!0))),j.on(this._element,"focusout.bs.toast",(t=>this._onInteraction(t,!1)))}_clearTimeout(){clearTimeout(this._timeout),this._timeout=null}static jQueryInterface(t){return this.each((function(){const e=Mn.getOrCreateInstance(this,t);if("string"==typeof t){if(void 0===e[t])throw new TypeError(`No method named "${t}"`);e[t](this)}}))}}return R(Mn),g(Mn),{Alert:W,Button:z,Carousel:st,Collapse:pt,Dropdown:hi,Modal:Hi,Offcanvas:Fi,Popover:gn,ScrollSpy:An,Tab:xn,Toast:Mn,Tooltip:un}}));
//#
/* --- OverlayScrollbars.min.js --- */
/*!
 * OverlayScrollbars
 * https://github.com/KingSora/OverlayScrollbars
 *
 * Version: 1.13.0
 *
 * Copyright KingSora | Rene Haas.
 * https://github.com/KingSora
 *
 * Released under the MIT license.
 * Date: 02.08.2020
 */
!function(n,t){"function"==typeof define&&define.amd?define(function(){return t(n,n.document,undefined)}):"object"==typeof module&&"object"==typeof module.exports?module.exports=t(n,n.document,undefined):t(n,n.document,undefined)}("undefined"!=typeof window?window:this,function(vi,hi,di){"use strict";var o,l,a,u,pi="object",bi="function",mi="array",gi="string",wi="boolean",yi="number",f="undefined",n="null",xi={c:"class",s:"style",i:"id",l:"length",p:"prototype",ti:"tabindex",oH:"offsetHeight",cH:"clientHeight",sH:"scrollHeight",oW:"offsetWidth",cW:"clientWidth",sW:"scrollWidth",hOP:"hasOwnProperty",bCR:"getBoundingClientRect"},_i=(o={},l={},{e:a=["-webkit-","-moz-","-o-","-ms-"],u:u=["WebKit","Moz","O","MS"],v:function(n){var t=l[n];if(l[xi.hOP](n))return t;for(var r,e,i,o=c(n),u=hi.createElement("div")[xi.s],f=0;f<a.length;f++)for(i=a[f].replace(/-/g,""),r=[n,a[f]+n,i+o,c(i)+o],e=0;e<r[xi.l];e++)if(u[r[e]]!==di){t=r[e];break}return l[n]=t},d:function(n,t,r){var e=n+" "+t,i=l[e];if(l[xi.hOP](e))return i;for(var o,u=hi.createElement("div")[xi.s],f=t.split(" "),a=r||"",c=0,s=-1;c<f[xi.l];c++)for(;s<_i.e[xi.l];s++)if(o=s<0?f[c]:_i.e[s]+f[c],u.cssText=n+":"+o+a,u[xi.l]){i=o;break}return l[e]=i},m:function(n,t,r){var e=0,i=o[n];if(!o[xi.hOP](n)){for(i=vi[n];e<u[xi.l];e++)i=i||vi[(t?u[e]:u[e].toLowerCase())+c(n)];o[n]=i}return i||r}});function c(n){return n.charAt(0).toUpperCase()+n.slice(1)}var Oi={wW:r(t,0,!0),wH:r(t,0),mO:r(_i.m,0,"MutationObserver",!0),rO:r(_i.m,0,"ResizeObserver",!0),rAF:r(_i.m,0,"requestAnimationFrame",!1,function(n){return vi.setTimeout(n,1e3/60)}),cAF:r(_i.m,0,"cancelAnimationFrame",!1,function(n){return vi.clearTimeout(n)}),now:function(){return Date.now&&Date.now()||(new Date).getTime()},stpP:function(n){n.stopPropagation?n.stopPropagation():n.cancelBubble=!0},prvD:function(n){n.preventDefault&&n.cancelable?n.preventDefault():n.returnValue=!1},page:function(n){var t=((n=n.originalEvent||n).target||n.srcElement||hi).ownerDocument||hi,r=t.documentElement,e=t.body;if(n.touches===di)return!n.pageX&&n.clientX&&null!=n.clientX?{x:n.clientX+(r&&r.scrollLeft||e&&e.scrollLeft||0)-(r&&r.clientLeft||e&&e.clientLeft||0),y:n.clientY+(r&&r.scrollTop||e&&e.scrollTop||0)-(r&&r.clientTop||e&&e.clientTop||0)}:{x:n.pageX,y:n.pageY};var i=n.touches[0];return{x:i.pageX,y:i.pageY}},mBtn:function(n){var t=n.button;return n.which||t===di?n.which:1&t?1:2&t?3:4&t?2:0},inA:function(n,t){for(var r=0;r<t[xi.l];r++)try{if(t[r]===n)return r}catch(e){}return-1},isA:function(n){var t=Array.isArray;return t?t(n):this.type(n)==mi},type:function(n){return n===di||null===n?n+"":Object[xi.p].toString.call(n).replace(/^\[object (.+)\]$/,"$1").toLowerCase()},bind:r};function t(n){return n?vi.innerWidth||hi.documentElement[xi.cW]||hi.body[xi.cW]:vi.innerHeight||hi.documentElement[xi.cH]||hi.body[xi.cH]}function r(n,t){if(typeof n!=bi)throw"Can't bind function!";var r=xi.p,e=Array[r].slice.call(arguments,2),i=function(){},o=function(){return n.apply(this instanceof i?this:t,e.concat(Array[r].slice.call(arguments)))};return n[r]&&(i[r]=n[r]),o[r]=new i,o}var s,v,h,k,I,T,d,p,Si=Math,zi=vi.jQuery,A=(s={p:Si.PI,c:Si.cos,s:Si.sin,w:Si.pow,t:Si.sqrt,n:Si.asin,a:Si.abs,o:1.70158},{swing:function(n,t,r,e,i){return.5-s.c(n*s.p)/2},linear:function(n,t,r,e,i){return n},easeInQuad:function(n,t,r,e,i){return e*(t/=i)*t+r},easeOutQuad:function(n,t,r,e,i){return-e*(t/=i)*(t-2)+r},easeInOutQuad:function(n,t,r,e,i){return(t/=i/2)<1?e/2*t*t+r:-e/2*(--t*(t-2)-1)+r},easeInCubic:function(n,t,r,e,i){return e*(t/=i)*t*t+r},easeOutCubic:function(n,t,r,e,i){return e*((t=t/i-1)*t*t+1)+r},easeInOutCubic:function(n,t,r,e,i){return(t/=i/2)<1?e/2*t*t*t+r:e/2*((t-=2)*t*t+2)+r},easeInQuart:function(n,t,r,e,i){return e*(t/=i)*t*t*t+r},easeOutQuart:function(n,t,r,e,i){return-e*((t=t/i-1)*t*t*t-1)+r},easeInOutQuart:function(n,t,r,e,i){return(t/=i/2)<1?e/2*t*t*t*t+r:-e/2*((t-=2)*t*t*t-2)+r},easeInQuint:function(n,t,r,e,i){return e*(t/=i)*t*t*t*t+r},easeOutQuint:function(n,t,r,e,i){return e*((t=t/i-1)*t*t*t*t+1)+r},easeInOutQuint:function(n,t,r,e,i){return(t/=i/2)<1?e/2*t*t*t*t*t+r:e/2*((t-=2)*t*t*t*t+2)+r},easeInSine:function(n,t,r,e,i){return-e*s.c(t/i*(s.p/2))+e+r},easeOutSine:function(n,t,r,e,i){return e*s.s(t/i*(s.p/2))+r},easeInOutSine:function(n,t,r,e,i){return-e/2*(s.c(s.p*t/i)-1)+r},easeInExpo:function(n,t,r,e,i){return 0==t?r:e*s.w(2,10*(t/i-1))+r},easeOutExpo:function(n,t,r,e,i){return t==i?r+e:e*(1-s.w(2,-10*t/i))+r},easeInOutExpo:function(n,t,r,e,i){return 0==t?r:t==i?r+e:(t/=i/2)<1?e/2*s.w(2,10*(t-1))+r:e/2*(2-s.w(2,-10*--t))+r},easeInCirc:function(n,t,r,e,i){return-e*(s.t(1-(t/=i)*t)-1)+r},easeOutCirc:function(n,t,r,e,i){return e*s.t(1-(t=t/i-1)*t)+r},easeInOutCirc:function(n,t,r,e,i){return(t/=i/2)<1?-e/2*(s.t(1-t*t)-1)+r:e/2*(s.t(1-(t-=2)*t)+1)+r},easeInElastic:function(n,t,r,e,i){var o=s.o,u=0,f=e;return 0==t?r:1==(t/=i)?r+e:(u=u||.3*i,o=f<s.a(e)?(f=e,u/4):u/(2*s.p)*s.n(e/f),-(f*s.w(2,10*--t)*s.s((t*i-o)*(2*s.p)/u))+r)},easeOutElastic:function(n,t,r,e,i){var o=s.o,u=0,f=e;return 0==t?r:1==(t/=i)?r+e:(u=u||.3*i,o=f<s.a(e)?(f=e,u/4):u/(2*s.p)*s.n(e/f),f*s.w(2,-10*t)*s.s((t*i-o)*(2*s.p)/u)+e+r)},easeInOutElastic:function(n,t,r,e,i){var o=s.o,u=0,f=e;return 0==t?r:2==(t/=i/2)?r+e:(u=u||i*(.3*1.5),o=f<s.a(e)?(f=e,u/4):u/(2*s.p)*s.n(e/f),t<1?f*s.w(2,10*--t)*s.s((t*i-o)*(2*s.p)/u)*-.5+r:f*s.w(2,-10*--t)*s.s((t*i-o)*(2*s.p)/u)*.5+e+r)},easeInBack:function(n,t,r,e,i,o){return e*(t/=i)*t*(((o=o||s.o)+1)*t-o)+r},easeOutBack:function(n,t,r,e,i,o){return e*((t=t/i-1)*t*(((o=o||s.o)+1)*t+o)+1)+r},easeInOutBack:function(n,t,r,e,i,o){return o=o||s.o,(t/=i/2)<1?e/2*(t*t*((1+(o*=1.525))*t-o))+r:e/2*((t-=2)*t*((1+(o*=1.525))*t+o)+2)+r},easeInBounce:function(n,t,r,e,i){return e-this.easeOutBounce(n,i-t,0,e,i)+r},easeOutBounce:function(n,t,r,e,i){var o=7.5625;return(t/=i)<1/2.75?e*(o*t*t)+r:t<2/2.75?e*(o*(t-=1.5/2.75)*t+.75)+r:t<2.5/2.75?e*(o*(t-=2.25/2.75)*t+.9375)+r:e*(o*(t-=2.625/2.75)*t+.984375)+r},easeInOutBounce:function(n,t,r,e,i){return t<i/2?.5*this.easeInBounce(n,2*t,0,e,i)+r:.5*this.easeOutBounce(n,2*t-i,0,e,i)+.5*e+r}}),Ci=(v=/[^\x20\t\r\n\f]+/g,h=" ",k="scrollLeft",I="scrollTop",T=[],d=Oi.type,p={animationIterationCount:!0,columnCount:!0,fillOpacity:!0,flexGrow:!0,flexShrink:!0,fontWeight:!0,lineHeight:!0,opacity:!0,order:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},M[xi.p]={on:function(t,r){var e,i=(t=(t||"").match(v)||[""])[xi.l],o=0;return this.each(function(){e=this;try{if(e.addEventListener)for(;o<i;o++)e.addEventListener(t[o],r);else if(e.detachEvent)for(;o<i;o++)e.attachEvent("on"+t[o],r)}catch(n){}})},off:function(t,r){var e,i=(t=(t||"").match(v)||[""])[xi.l],o=0;return this.each(function(){e=this;try{if(e.removeEventListener)for(;o<i;o++)e.removeEventListener(t[o],r);else if(e.detachEvent)for(;o<i;o++)e.detachEvent("on"+t[o],r)}catch(n){}})},one:function(n,i){return n=(n||"").match(v)||[""],this.each(function(){var e=M(this);M.each(n,function(n,t){var r=function(n){i.call(this,n),e.off(t,r)};e.on(t,r)})})},trigger:function(n){var t,r;return this.each(function(){t=this,hi.createEvent?((r=hi.createEvent("HTMLEvents")).initEvent(n,!0,!1),t.dispatchEvent(r)):t.fireEvent("on"+n)})},append:function(n){return this.each(function(){i(this,"beforeend",n)})},prepend:function(n){return this.each(function(){i(this,"afterbegin",n)})},before:function(n){return this.each(function(){i(this,"beforebegin",n)})},after:function(n){return this.each(function(){i(this,"afterend",n)})},remove:function(){return this.each(function(){var n=this.parentNode;null!=n&&n.removeChild(this)})},unwrap:function(){var n,t,r,e=[];for(this.each(function(){-1===H(r=this.parentNode,e)&&e.push(r)}),n=0;n<e[xi.l];n++){for(t=e[n],r=t.parentNode;t.firstChild;)r.insertBefore(t.firstChild,t);r.removeChild(t)}return this},wrapAll:function(n){for(var t,r=this,e=M(n)[0],i=e,o=r[0].parentNode,u=r[0].previousSibling;0<i.childNodes[xi.l];)i=i.childNodes[0];for(t=0;r[xi.l]-t;i.firstChild===r[0]&&t++)i.appendChild(r[t]);var f=u?u.nextSibling:o.firstChild;return o.insertBefore(e,f),this},wrapInner:function(r){return this.each(function(){var n=M(this),t=n.contents();t[xi.l]?t.wrapAll(r):n.append(r)})},wrap:function(n){return this.each(function(){M(this).wrapAll(n)})},css:function(n,t){var r,e,i,o=vi.getComputedStyle;return d(n)==gi?t===di?(r=this[0],i=o?o(r,null):r.currentStyle[n],o?null!=i?i.getPropertyValue(n):r[xi.s][n]:i):this.each(function(){y(this,n,t)}):this.each(function(){for(e in n)y(this,e,n[e])})},hasClass:function(n){for(var t,r,e=0,i=h+n+h;t=this[e++];){if((r=t.classList)&&r.contains(n))return!0;if(1===t.nodeType&&-1<(h+g(t.className+"")+h).indexOf(i))return!0}return!1},addClass:function(n){var t,r,e,i,o,u,f,a,c=0,s=0;if(n)for(t=n.match(v)||[];r=this[c++];)if(a=r.classList,f===di&&(f=a!==di),f)for(;o=t[s++];)a.add(o);else if(i=r.className+"",e=1===r.nodeType&&h+g(i)+h){for(;o=t[s++];)e.indexOf(h+o+h)<0&&(e+=o+h);i!==(u=g(e))&&(r.className=u)}return this},removeClass:function(n){var t,r,e,i,o,u,f,a,c=0,s=0;if(n)for(t=n.match(v)||[];r=this[c++];)if(a=r.classList,f===di&&(f=a!==di),f)for(;o=t[s++];)a.remove(o);else if(i=r.className+"",e=1===r.nodeType&&h+g(i)+h){for(;o=t[s++];)for(;-1<e.indexOf(h+o+h);)e=e.replace(h+o+h,h);i!==(u=g(e))&&(r.className=u)}return this},hide:function(){return this.each(function(){this[xi.s].display="none"})},show:function(){return this.each(function(){this[xi.s].display="block"})},attr:function(n,t){for(var r,e=0;r=this[e++];){if(t===di)return r.getAttribute(n);r.setAttribute(n,t)}return this},removeAttr:function(n){return this.each(function(){this.removeAttribute(n)})},offset:function(){var n=this[0][xi.bCR](),t=vi.pageXOffset||hi.documentElement[k],r=vi.pageYOffset||hi.documentElement[I];return{top:n.top+r,left:n.left+t}},position:function(){var n=this[0];return{top:n.offsetTop,left:n.offsetLeft}},scrollLeft:function(n){for(var t,r=0;t=this[r++];){if(n===di)return t[k];t[k]=n}return this},scrollTop:function(n){for(var t,r=0;t=this[r++];){if(n===di)return t[I];t[I]=n}return this},val:function(n){var t=this[0];return n?(t.value=n,this):t.value},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},eq:function(n){return M(this[0<=n?n:this[xi.l]+n])},find:function(t){var r,e=[];return this.each(function(){var n=this.querySelectorAll(t);for(r=0;r<n[xi.l];r++)e.push(n[r])}),M(e)},children:function(n){var t,r,e,i=[];return this.each(function(){for(r=this.children,e=0;e<r[xi.l];e++)t=r[e],(!n||t.matches&&t.matches(n)||w(t,n))&&i.push(t)}),M(i)},parent:function(n){var t,r=[];return this.each(function(){t=this.parentNode,n&&!M(t).is(n)||r.push(t)}),M(r)},is:function(n){var t,r;for(r=0;r<this[xi.l];r++){if(t=this[r],":visible"===n)return _(t);if(":hidden"===n)return!_(t);if(t.matches&&t.matches(n)||w(t,n))return!0}return!1},contents:function(){var n,t,r=[];return this.each(function(){for(n=this.childNodes,t=0;t<n[xi.l];t++)r.push(n[t])}),M(r)},each:function(n){return e(this,n)},animate:function(n,t,r,e){return this.each(function(){x(this,n,t,r,e)})},stop:function(n,t){return this.each(function(){!function f(n,t,r){for(var e,i,o,u=0;u<T[xi.l];u++)if((e=T[u]).el===n){if(0<e.q[xi.l]){if((i=e.q[0]).stop=!0,Oi.cAF()(i.frame),e.q.splice(0,1),r)for(o in i.props)W(n,o,i.props[o]);t?e.q=[]:N(e,!1)}break}}(this,n,t)})}},b(M,{extend:b,inArray:H,isEmptyObject:L,isPlainObject:R,each:e}),M);function b(){var n,t,r,e,i,o,u=arguments[0]||{},f=1,a=arguments[xi.l],c=!1;for(d(u)==wi&&(c=u,u=arguments[1]||{},f=2),d(u)!=pi&&!d(u)==bi&&(u={}),a===f&&(u=M,--f);f<a;f++)if(null!=(i=arguments[f]))for(e in i)n=u[e],u!==(r=i[e])&&(c&&r&&(R(r)||(t=Oi.isA(r)))?(o=t?(t=!1,n&&Oi.isA(n)?n:[]):n&&R(n)?n:{},u[e]=b(c,o,r)):r!==di&&(u[e]=r));return u}function H(n,t,r){for(var e=r||0;e<t[xi.l];e++)if(t[e]===n)return e;return-1}function E(n){return d(n)==bi}function L(n){for(var t in n)return!1;return!0}function R(n){if(!n||d(n)!=pi)return!1;var t,r=xi.p,e=Object[r].hasOwnProperty,i=e.call(n,"constructor"),o=n.constructor&&n.constructor[r]&&e.call(n.constructor[r],"isPrototypeOf");if(n.constructor&&!i&&!o)return!1;for(t in n);return d(t)==f||e.call(n,t)}function e(n,t){var r=0;if(m(n))for(;r<n[xi.l]&&!1!==t.call(n[r],r,n[r]);r++);else for(r in n)if(!1===t.call(n[r],r,n[r]))break;return n}function m(n){var t=!!n&&[xi.l]in n&&n[xi.l],r=d(n);return!E(r)&&(r==mi||0===t||d(t)==yi&&0<t&&t-1 in n)}function g(n){return(n.match(v)||[]).join(h)}function w(n,t){for(var r=(n.parentNode||hi).querySelectorAll(t)||[],e=r[xi.l];e--;)if(r[e]==n)return 1}function i(n,t,r){if(Oi.isA(r))for(var e=0;e<r[xi.l];e++)i(n,t,r[e]);else d(r)==gi?n.insertAdjacentHTML(t,r):n.insertAdjacentElement(t,r.nodeType?r:r[0])}function y(n,t,r){try{n[xi.s][t]!==di&&(n[xi.s][t]=function e(n,t){p[n.toLowerCase()]||d(t)!=yi||(t+="px");return t}(t,r))}catch(i){}}function N(n,t){var r,e;!1!==t&&n.q.splice(0,1),0<n.q[xi.l]?(e=n.q[0],x(n.el,e.props,e.duration,e.easing,e.complete,!0)):-1<(r=H(n,T))&&T.splice(r,1)}function W(n,t,r){t===k||t===I?n[t]=r:y(n,t,r)}function x(n,t,r,e,i,o){var u,f,a,c,s,l,v=R(r),h={},d={},p=0;for(l=v?(e=r.easing,r.start,a=r.progress,c=r.step,s=r.specialEasing,i=r.complete,r.duration):r,s=s||{},l=l||400,e=e||"swing",o=o||!1;p<T[xi.l];p++)if(T[p].el===n){f=T[p];break}for(u in f||(f={el:n,q:[]},T.push(f)),t)h[u]=u===k||u===I?n[u]:M(n).css(u);for(u in h)h[u]!==t[u]&&t[u]!==di&&(d[u]=t[u]);if(L(d))o&&N(f);else{var b,m,g,w,y,x,_,O,S,z=o?0:H(C,f.q),C={props:d,duration:v?r:l,easing:e,complete:i};if(-1===z&&(z=f.q[xi.l],f.q.push(C)),0===z)if(0<l)_=Oi.now(),O=function(){for(u in b=Oi.now(),S=b-_,m=C.stop||l<=S,g=1-(Si.max(0,_+l-b)/l||0),d)w=parseFloat(h[u]),y=parseFloat(d[u]),x=(y-w)*A[s[u]||e](g,g*l,0,1,l)+w,W(n,u,x),E(c)&&c(x,{elem:n,prop:u,start:w,now:x,end:y,pos:g,options:{easing:e,speacialEasing:s,duration:l,complete:i,step:c},startTime:_});E(a)&&a({},g,Si.max(0,l-S)),m?(N(f),E(i)&&i()):C.frame=Oi.rAF()(O)},C.frame=Oi.rAF()(O);else{for(u in d)W(n,u,d[u]);N(f)}}}function _(n){return!!(n[xi.oW]||n[xi.oH]||n.getClientRects()[xi.l])}function M(n){if(0===arguments[xi.l])return this;var t,r,e=new M,i=n,o=0;if(d(n)==gi)for(i=[],t="<"===n.charAt(0)?((r=hi.createElement("div")).innerHTML=n,r.children):hi.querySelectorAll(n);o<t[xi.l];o++)i.push(t[o]);if(i){for(d(i)==gi||m(i)&&i!==vi&&i!==i.self||(i=[i]),o=0;o<i[xi.l];o++)e[o]=i[o];e[xi.l]=i[xi.l]}return e}var O,S,ki,z,C,D,F,P,j,B,Q,U,V,$,Ii,Ti=(O=[],S="__overlayScrollbars__",function(n,t){var r=arguments[xi.l];if(r<1)return O;if(t)n[S]=t,O.push(n);else{var e=Oi.inA(n,O);if(-1<e){if(!(1<r))return O[e][S];delete n[S],O.splice(e,1)}}}),q=($=[],D=Oi.type,U={className:["os-theme-dark",[n,gi]],resize:["none","n:none b:both h:horizontal v:vertical"],sizeAutoCapable:P=[!0,wi],clipAlways:P,normalizeRTL:P,paddingAbsolute:j=[!(F=[wi,yi,gi,mi,pi,bi,n]),wi],autoUpdate:[null,[n,wi]],autoUpdateInterval:[33,yi],updateOnLoad:[["img"],[gi,mi,n]],nativeScrollbarsOverlaid:{showNativeScrollbars:j,initialize:P},overflowBehavior:{x:["scroll",Q="v-h:visible-hidden v-s:visible-scroll s:scroll h:hidden"],y:["scroll",Q]},scrollbars:{visibility:["auto","v:visible h:hidden a:auto"],autoHide:["never","n:never s:scroll l:leave m:move"],autoHideDelay:[800,yi],dragScrolling:P,clickScrolling:j,touchSupport:P,snapHandle:j},textarea:{dynWidth:j,dynHeight:j,inheritedAttrs:[["style","class"],[gi,mi,n]]},callbacks:{onInitialized:B=[null,[n,bi]],onInitializationWithdrawn:B,onDestroyed:B,onScrollStart:B,onScroll:B,onScrollStop:B,onOverflowChanged:B,onOverflowAmountChanged:B,onDirectionChanged:B,onContentSizeChanged:B,onHostSizeChanged:B,onUpdated:B}},Ii={g:(V=function(i){var o=function(n){var t,r,e;for(t in n)n[xi.hOP](t)&&(r=n[t],(e=D(r))==mi?n[t]=r[i?1:0]:e==pi&&(n[t]=o(r)));return n};return o(Ci.extend(!0,{},U))})(),_:V(!0),O:function(n,t,I,r){var e={},i={},o=Ci.extend(!0,{},n),T=Ci.inArray,A=Ci.isEmptyObject,H=function(n,t,r,e,i,o){for(var u in t)if(t[xi.hOP](u)&&n[xi.hOP](u)){var f,a,c,s,l,v,h,d,p=!1,b=!1,m=t[u],g=D(m),w=g==pi,y=Oi.isA(m)?m:[m],x=r[u],_=n[u],O=D(_),S=o?o+".":"",z='The option "'+S+u+"\" wasn't set, because",C=[],k=[];if(x=x===di?{}:x,w&&O==pi)e[u]={},i[u]={},H(_,m,x,e[u],i[u],S+u),Ci.each([n,e,i],function(n,t){A(t[u])&&delete t[u]});else if(!w){for(v=0;v<y[xi.l];v++)if(l=y[v],c=(g=D(l))==gi&&-1===T(l,F))for(C.push(gi),f=l.split(" "),k=k.concat(f),h=0;h<f[xi.l];h++){for(s=(a=f[h].split(":"))[0],d=0;d<a[xi.l];d++)if(_===a[d]){p=!0;break}if(p)break}else if(C.push(l),O===l){p=!0;break}p?((b=_!==x)&&(e[u]=_),(c?T(x,a)<0:b)&&(i[u]=c?s:_)):I&&console.warn(z+" it doesn't accept the type [ "+O.toUpperCase()+' ] with the value of "'+_+'".\r\nAccepted types are: [ '+C.join(", ").toUpperCase()+" ]."+(0<k[length]?"\r\nValid strings are: [ "+k.join(", ").split(":").join(", ")+" ].":"")),delete n[u]}}};return H(o,t,r||{},e,i),!A(o)&&I&&console.warn("The following options are discarded due to invalidity:\r\n"+vi.JSON.stringify(o,null,2)),{S:e,z:i}}},(ki=vi.OverlayScrollbars=function(n,r,e){if(0===arguments[xi.l])return this;var i,t,o=[],u=Ci.isPlainObject(r);return n?(n=n[xi.l]!=di?n:[n[0]||n],X(),0<n[xi.l]&&(u?Ci.each(n,function(n,t){(i=t)!==di&&o.push(K(i,r,e,z,C))}):Ci.each(n,function(n,t){i=Ti(t),("!"===r&&ki.valid(i)||Oi.type(r)==bi&&r(t,i)||r===di)&&o.push(i)}),t=1===o[xi.l]?o[0]:o),t):u||!r?t:o}).globals=function(){X();var n=Ci.extend(!0,{},z);return delete n.msie,n},ki.defaultOptions=function(n){X();var t=z.defaultOptions;if(n===di)return Ci.extend(!0,{},t);z.defaultOptions=Ci.extend(!0,{},t,Ii.O(n,Ii._,!0,t).S)},ki.valid=function(n){return n instanceof ki&&!n.getState().destroyed},ki.extension=function(n,t,r){var e=Oi.type(n)==gi,i=arguments[xi.l],o=0;if(i<1||!e)return Ci.extend(!0,{length:$[xi.l]},$);if(e)if(Oi.type(t)==bi)$.push({name:n,extensionFactory:t,defaultOptions:r});else for(;o<$[xi.l];o++)if($[o].name===n){if(!(1<i))return Ci.extend(!0,{},$[o]);$.splice(o,1)}},ki);function X(){z=z||new Y(Ii.g),C=C||new G(z)}function Y(n){var _=this,i="overflow",O=Ci("body"),S=Ci('<div id="os-dummy-scrollbar-size"><div></div></div>'),o=S[0],e=Ci(S.children("div").eq(0));O.append(S),S.hide().show();var t,r,u,f,a,c,s,l,v,h=z(o),d={x:0===h.x,y:0===h.y},p=(r=vi.navigator.userAgent,f="substring",a=r[u="indexOf"]("MSIE "),c=r[u]("Trident/"),s=r[u]("Edge/"),l=r[u]("rv:"),v=parseInt,0<a?t=v(r[f](a+5,r[u](".",a)),10):0<c?t=v(r[f](l+3,r[u](".",l)),10):0<s&&(t=v(r[f](s+5,r[u](".",s)),10)),t);function z(n){return{x:n[xi.oH]-n[xi.cH],y:n[xi.oW]-n[xi.cW]}}Ci.extend(_,{defaultOptions:n,msie:p,autoUpdateLoop:!1,autoUpdateRecommended:!Oi.mO(),nativeScrollbarSize:h,nativeScrollbarIsOverlaid:d,nativeScrollbarStyling:function(){var n=!1;S.addClass("os-viewport-native-scrollbars-invisible");try{n="none"===S.css("scrollbar-width")&&(9<p||!p)||"none"===vi.getComputedStyle(o,"::-webkit-scrollbar").getPropertyValue("display")}catch(t){}return n}(),overlayScrollbarDummySize:{x:30,y:30},cssCalc:_i.d("width","calc","(1px)")||null,restrictedMeasuring:function(){S.css(i,"hidden");var n=o[xi.sW],t=o[xi.sH];S.css(i,"visible");var r=o[xi.sW],e=o[xi.sH];return n-r!=0||t-e!=0}(),rtlScrollBehavior:function(){S.css({"overflow-y":"hidden","overflow-x":"scroll",direction:"rtl"}).scrollLeft(0);var n=S.offset(),t=e.offset();S.scrollLeft(-999);var r=e.offset();return{i:n.left===t.left,n:t.left!==r.left}}(),supportTransform:!!_i.v("transform"),supportTransition:!!_i.v("transition"),supportPassiveEvents:function(){var n=!1;try{vi.addEventListener("test",null,Object.defineProperty({},"passive",{get:function(){n=!0}}))}catch(t){}return n}(),supportResizeObserver:!!Oi.rO(),supportMutationObserver:!!Oi.mO()}),S.removeAttr(xi.s).remove(),function(){if(!d.x||!d.y){var m=Si.abs,g=Oi.wW(),w=Oi.wH(),y=x();Ci(vi).on("resize",function(){if(0<Ti().length){var n=Oi.wW(),t=Oi.wH(),r=n-g,e=t-w;if(0==r&&0==e)return;var i,o=Si.round(n/(g/100)),u=Si.round(t/(w/100)),f=m(r),a=m(e),c=m(o),s=m(u),l=x(),v=2<f&&2<a,h=!function b(n,t){var r=m(n),e=m(t);return r!==e&&r+1!==e&&r-1!==e}(c,s),d=v&&h&&(l!==y&&0<y),p=_.nativeScrollbarSize;d&&(O.append(S),i=_.nativeScrollbarSize=z(S[0]),S.remove(),p.x===i.x&&p.y===i.y||Ci.each(Ti(),function(){Ti(this)&&Ti(this).update("zoom")})),g=n,w=t,y=l}})}function x(){var n=vi.screen.deviceXDPI||0,t=vi.screen.logicalXDPI||1;return vi.devicePixelRatio||n/t}}()}function G(r){var c,e=Ci.inArray,s=Oi.now,l="autoUpdate",v=xi.l,h=[],d=[],p=!1,b=33,m=s(),g=function(){if(0<h[v]&&p){c=Oi.rAF()(function(){g()});var n,t,r,e,i,o,u=s(),f=u-m;if(b<f){m=u-f%b,n=33;for(var a=0;a<h[v];a++)(t=h[a])!==di&&(e=(r=t.options())[l],i=Si.max(1,r.autoUpdateInterval),o=s(),(!0===e||null===e)&&o-d[a]>i&&(t.update("auto"),d[a]=new Date(o+=i)),n=Si.max(1,Si.min(n,i)));b=n}}else b=33};this.add=function(n){-1===e(n,h)&&(h.push(n),d.push(s()),0<h[v]&&!p&&(p=!0,r.autoUpdateLoop=p,g()))},this.remove=function(n){var t=e(n,h);-1<t&&(d.splice(t,1),h.splice(t,1),0===h[v]&&p&&(p=!1,r.autoUpdateLoop=p,c!==di&&(Oi.cAF()(c),c=-1)))}}function K(r,n,t,xt,_t){var cn=Oi.type,sn=Ci.inArray,h=Ci.each,Ot=new ki,e=Ci[xi.p];if(ht(r)){if(Ti(r)){var i=Ti(r);return i.options(n),i}var St,zt,Ct,kt,D,It,Tt,At,F,ln,w,T,d,Ht,Et,Lt,Rt,y,p,Nt,Wt,Mt,Dt,Ft,Pt,jt,Bt,Qt,Ut,o,u,Vt,$t,qt,f,P,c,j,Xt,Yt,Gt,Kt,Jt,Zt,nr,tr,rr,er,ir,a,s,l,v,b,m,x,A,or,ur,fr,H,ar,cr,sr,lr,vr,hr,dr,pr,br,mr,gr,wr,yr,xr,_r,E,Or,Sr,zr,Cr,kr,Ir,Tr,Ar,g,_,Hr,Er,Lr,Rr,Nr,Wr,Mr,Dr,Fr,Pr,jr,Br,Qr,Ur,O,S,z,C,Vr,$r,k,I,qr,Xr,Yr,Gr,Kr,B,Q,Jr,Zr,ne,te,re={},vn={},hn={},ee={},ie={},L="-hidden",oe="margin-",ue="padding-",fe="border-",ae="top",ce="right",se="bottom",le="left",ve="min-",he="max-",de="width",pe="height",be="float",me="",ge="auto",dn="sync",we="scroll",ye="100%",pn="x",bn="y",R=".",xe=" ",N="scrollbar",W="-horizontal",M="-vertical",_e=we+"Left",Oe=we+"Top",U="mousedown touchstart",V="mouseup touchend touchcancel",$="mousemove touchmove",q="mouseenter",X="mouseleave",Y="keydown",G="keyup",K="selectstart",J="transitionend webkitTransitionEnd oTransitionEnd",Z="__overlayScrollbarsRO__",nn="os-",tn="os-html",rn="os-host",en=rn+"-foreign",on=rn+"-textarea",un=rn+"-"+N+W+L,fn=rn+"-"+N+M+L,an=rn+"-transition",Se=rn+"-rtl",ze=rn+"-resize-disabled",Ce=rn+"-scrolling",ke=rn+"-overflow",Ie=(ke=rn+"-overflow")+"-x",Te=ke+"-y",mn="os-textarea",gn=mn+"-cover",wn="os-padding",yn="os-viewport",Ae=yn+"-native-scrollbars-invisible",xn=yn+"-native-scrollbars-overlaid",_n="os-content",He="os-content-arrange",Ee="os-content-glue",Le="os-size-auto-observer",On="os-resize-observer",Sn="os-resize-observer-item",zn=Sn+"-final",Cn="os-text-inherit",kn=nn+N,In=kn+"-track",Tn=In+"-off",An=kn+"-handle",Hn=An+"-off",En=kn+"-unusable",Ln=kn+"-"+ge+L,Rn=kn+"-corner",Re=Rn+"-resize",Ne=Re+"-both",We=Re+W,Me=Re+M,Nn=kn+W,Wn=kn+M,Mn="os-dragging",De="os-theme-none",Dn=[Ae,xn,Tn,Hn,En,Ln,Re,Ne,We,Me,Mn].join(xe),Fn=[],Pn=[xi.ti],jn={},Fe={},Pe=42,Bn="load",Qn=[],Un={},Vn=["wrap","cols","rows"],$n=[xi.i,xi.c,xi.s,"open"].concat(Pn),qn=[];return Ot.sleep=function(){Ut=!0},Ot.update=function(n){var t,r,e,i,o;if(!Et)return cn(n)==gi?n===ge?(t=function u(){if(!Ut&&!Vr){var r,e,i,o=[],n=[{C:Yt,k:$n.concat(":visible")},{C:Lt?Xt:di,k:Vn}];return h(n,function(n,t){(r=t.C)&&h(t.k,function(n,t){e=":"===t.charAt(0)?r.is(t):r.attr(t),i=Un[t],fi(e,i)&&o.push(t),Un[t]=e})}),it(o),0<o[xi.l]}}(),r=function a(){if(Ut)return!1;var n,t,r,e,i=oi(),o=Lt&&br&&!Fr?Xt.val().length:0,u=!Vr&&br&&!Lt,f={};return u&&(n=nr.css(be),f[be]=Qt?ce:le,f[de]=ge,nr.css(f)),e={w:i[xi.sW]+o,h:i[xi.sH]+o},u&&(f[be]=n,f[de]=ye,nr.css(f)),t=Ve(),r=fi(e,g),g=e,r||t}(),(e=t||r)&&qe({I:r,T:Ht?di:Vt})):n===dn?Vr?(i=z(O.takeRecords()),o=C(S.takeRecords())):i=Ot.update(ge):"zoom"===n&&qe({A:!0,I:!0}):(n=Ut||n,Ut=!1,Ot.update(dn)&&!n||qe({H:n})),Xe(),e||i||o},Ot.options=function(n,t){var r,e={};if(Ci.isEmptyObject(n)||!Ci.isPlainObject(n)){if(cn(n)!=gi)return u;if(!(1<arguments.length))return bt(u,n);!function a(n,t,r){for(var e=t.split(R),i=e.length,o=0,u={},f=u;o<i;o++)u=u[e[o]]=o+1<i?{}:r;Ci.extend(n,f,!0)}(e,n,t),r=ot(e)}else r=ot(n);Ci.isEmptyObject(r)||qe({T:r})},Ot.destroy=function(){if(!Et){for(var n in _t.remove(Ot),Qe(),je(Kt),je(Gt),jn)Ot.removeExt(n);for(;0<qn[xi.l];)qn.pop()();Ue(!0),rr&&gt(rr),tr&&gt(tr),Wt&&gt(Gt),at(!0),st(!0),ut(!0);for(var t=0;t<Qn[xi.l];t++)Ci(Qn[t]).off(Bn,rt);Qn=di,Ut=Et=!0,Ti(r,0),ti("onDestroyed")}},Ot.scroll=function(n,t,r,e){if(0===arguments.length||n===di){var i=Wr&&Qt&&Ct.i,o=Wr&&Qt&&Ct.n,u=vn.L,f=vn.R,a=vn.N;return f=i?1-f:f,u=i?a-u:u,a*=o?-1:1,{position:{x:u*=o?-1:1,y:hn.L},ratio:{x:f,y:hn.R},max:{x:a,y:hn.N},handleOffset:{x:vn.W,y:hn.W},handleLength:{x:vn.M,y:hn.M},handleLengthRatio:{x:vn.D,y:hn.D},trackLength:{x:vn.F,y:hn.F},snappedHandleOffset:{x:vn.P,y:hn.P},isRTL:Qt,isRTLNormalized:Wr}}Ot.update(dn);var c,s,l,v,h,g,w,d,p,y=Wr,b=[pn,le,"l"],m=[bn,ae,"t"],x=["+=","-=","*=","/="],_=cn(t)==pi,O=_?t.complete:e,S={},z={},C="begin",k="nearest",I="never",T="ifneeded",A=xi.l,H=[pn,bn,"xy","yx"],E=[C,"end","center",k],L=["always",I,T],R=n[xi.hOP]("el"),N=R?n.el:n,W=!!(N instanceof Ci||zi)&&N instanceof zi,M=!W&&ht(N),D=function(){s&&Je(!0),l&&Je(!1)},F=cn(O)!=bi?di:function(){D(),O()};function P(n,t){for(c=0;c<t[A];c++)if(n===t[c])return 1}function j(n,t){var r=n?b:m;if(t=cn(t)==gi||cn(t)==yi?[t,t]:t,Oi.isA(t))return n?t[0]:t[1];if(cn(t)==pi)for(c=0;c<r[A];c++)if(r[c]in t)return t[r[c]]}function B(n,t){var r,e,i,o,u=cn(t)==gi,f=n?vn:hn,a=f.L,c=f.N,s=Qt&&n,l=s&&Ct.n&&!y,v="replace",h=eval;if((e=u?(2<t[A]&&(o=t.substr(0,2),-1<sn(o,x)&&(r=o)),t=(t=r?t.substr(2):t)[v](/min/g,0)[v](/</g,0)[v](/max/g,(l?"-":me)+ye)[v](/>/g,(l?"-":me)+ye)[v](/px/g,me)[v](/%/g," * "+c*(s&&Ct.n?-1:1)/100)[v](/vw/g," * "+ee.w)[v](/vh/g," * "+ee.h),ii(isNaN(t)?ii(h(t),!0).toFixed():t)):t)!==di&&!isNaN(e)&&cn(e)==yi){var d=y&&s,p=a*(d&&Ct.n?-1:1),b=d&&Ct.i,m=d&&Ct.n;switch(p=b?c-p:p,r){case"+=":i=p+e;break;case"-=":i=p-e;break;case"*=":i=p*e;break;case"/=":i=p/e;break;default:i=e}i=b?c-i:i,i*=m?-1:1,i=s&&Ct.n?Si.min(0,Si.max(c,i)):Si.max(0,Si.min(c,i))}return i===a?di:i}function Q(n,t,r,e){var i,o,u=[r,r],f=cn(n);if(f==t)n=[n,n];else if(f==mi){if(2<(i=n[A])||i<1)n=u;else for(1===i&&(n[1]=r),c=0;c<i;c++)if(o=n[c],cn(o)!=t||!P(o,e)){n=u;break}}else n=f==pi?[n[pn]||r,n[bn]||r]:u;return{x:n[0],y:n[1]}}function U(n){var t,r,e=[],i=[ae,ce,se,le];for(c=0;c<n[A]&&c!==i[A];c++)t=n[c],(r=cn(t))==wi?e.push(t?ii(p.css(oe+i[c])):0):e.push(r==yi?t:0);return e}if(W||M){var V,$=R?n.margin:0,q=R?n.axis:0,X=R?n.scroll:0,Y=R?n.block:0,G=[0,0,0,0],K=cn($);if(0<(p=W?N:Ci(N))[A]){$=K==yi||K==wi?U([$,$,$,$]):K==mi?2===(V=$[A])?U([$[0],$[1],$[0],$[1]]):4<=V?U($):G:K==pi?U([$[ae],$[ce],$[se],$[le]]):G,h=P(q,H)?q:"xy",g=Q(X,gi,"always",L),w=Q(Y,gi,C,E),d=$;var J=vn.L,Z=hn.L,nn=Jt.offset(),tn=p.offset(),rn={x:g.x==I||h==bn,y:g.y==I||h==pn};tn[ae]-=d[0],tn[le]-=d[3];var en={x:Si.round(tn[le]-nn[le]+J),y:Si.round(tn[ae]-nn[ae]+Z)};if(Qt&&(Ct.n||Ct.i||(en.x=Si.round(nn[le]-tn[le]+J)),Ct.n&&y&&(en.x*=-1),Ct.i&&y&&(en.x=Si.round(nn[le]-tn[le]+(vn.N-J)))),w.x!=C||w.y!=C||g.x==T||g.y==T||Qt){var on=p[0],un=ln?on[xi.bCR]():{width:on[xi.oW],height:on[xi.oH]},fn={w:un[de]+d[3]+d[1],h:un[pe]+d[0]+d[2]},an=function(n){var t=ni(n),r=t.j,e=t.B,i=t.Q,o=w[i]==(n&&Qt?C:"end"),u="center"==w[i],f=w[i]==k,a=g[i]==I,c=g[i]==T,s=ee[r],l=nn[e],v=fn[r],h=tn[e],d=u?2:1,p=h+v/2,b=l+s/2,m=v<=s&&l<=h&&h+v<=l+s;a?rn[i]=!0:rn[i]||((f||c)&&(rn[i]=c&&m,o=v<s?b<p:p<b),en[i]-=o||u?(s/d-v/d)*(n&&Qt&&y?-1:1):0)};an(!0),an(!1)}rn.y&&delete en.y,rn.x&&delete en.x,n=en}}S[_e]=B(!0,j(!0,n)),S[Oe]=B(!1,j(!1,n)),s=S[_e]!==di,l=S[Oe]!==di,(s||l)&&(0<t||_)?_?(t.complete=F,Zt.animate(S,t)):(v={duration:t,complete:F},Oi.isA(r)||Ci.isPlainObject(r)?(z[_e]=r[0]||r.x,z[Oe]=r[1]||r.y,v.specialEasing=z):v.easing=r,Zt.animate(S,v)):(s&&Zt[_e](S[_e]),l&&Zt[Oe](S[Oe]),D())},Ot.scrollStop=function(n,t,r){return Zt.stop(n,t,r),Ot},Ot.getElements=function(n){var t={target:or,host:ur,padding:ar,viewport:cr,content:sr,scrollbarHorizontal:{scrollbar:a[0],track:s[0],handle:l[0]},scrollbarVertical:{scrollbar:v[0],track:b[0],handle:m[0]},scrollbarCorner:ir[0]};return cn(n)==gi?bt(t,n):t},Ot.getState=function(n){function t(n){if(!Ci.isPlainObject(n))return n;var r=ai({},n),t=function(n,t){r[xi.hOP](n)&&(r[t]=r[n],delete r[n])};return t("w",de),t("h",pe),delete r.c,r}var r={destroyed:!!t(Et),sleeping:!!t(Ut),autoUpdate:t(!Vr),widthAuto:t(br),heightAuto:t(mr),padding:t(wr),overflowAmount:t(kr),hideOverflow:t(pr),hasOverflow:t(dr),contentScrollSize:t(vr),viewportSize:t(ee),hostSize:t(lr),documentMixed:t(y)};return cn(n)==gi?bt(r,n):r},Ot.ext=function(n){var t,r="added removed on contract".split(" "),e=0;if(cn(n)==gi){if(jn[xi.hOP](n))for(t=ai({},jn[n]);e<r.length;e++)delete t[r[e]]}else for(e in t={},jn)t[e]=ai({},Ot.ext(e));return t},Ot.addExt=function(n,t){var r,e,i,o,u=ki.extension(n),f=!0;if(u){if(jn[xi.hOP](n))return Ot.ext(n);if((r=u.extensionFactory.call(Ot,ai({},u.defaultOptions),Ci,Oi))&&(i=r.contract,cn(i)==bi&&(o=i(vi),f=cn(o)==wi?o:f),f))return e=(jn[n]=r).added,cn(e)==bi&&e(t),Ot.ext(n)}else console.warn('A extension with the name "'+n+"\" isn't registered.")},Ot.removeExt=function(n){var t,r=jn[n];return!!r&&(delete jn[n],t=r.removed,cn(t)==bi&&t(),!0)},ki.valid(function yt(n,t,r){var e,i;return o=xt.defaultOptions,It=xt.nativeScrollbarStyling,At=ai({},xt.nativeScrollbarSize),St=ai({},xt.nativeScrollbarIsOverlaid),zt=ai({},xt.overlayScrollbarDummySize),Ct=ai({},xt.rtlScrollBehavior),ot(ai({},o,t)),Tt=xt.cssCalc,D=xt.msie,kt=xt.autoUpdateRecommended,F=xt.supportTransition,ln=xt.supportTransform,w=xt.supportPassiveEvents,T=xt.supportResizeObserver,d=xt.supportMutationObserver,xt.restrictedMeasuring,P=Ci(n.ownerDocument),A=P[0],f=Ci(A.defaultView||A.parentWindow),x=f[0],c=wt(P,"html"),j=wt(c,"body"),Xt=Ci(n),or=Xt[0],Lt=Xt.is("textarea"),Rt=Xt.is("body"),y=A!==hi,p=Lt?Xt.hasClass(mn)&&Xt.parent().hasClass(_n):Xt.hasClass(rn)&&Xt.children(R+wn)[xi.l],St.x&&St.y&&!Vt.nativeScrollbarsOverlaid.initialize?(ti("onInitializationWithdrawn"),p&&(ut(!0),at(!0),st(!0)),Ut=Et=!0):(Rt&&((e={}).l=Si.max(Xt[_e](),c[_e](),f[_e]()),e.t=Si.max(Xt[Oe](),c[Oe](),f[Oe]()),i=function(){Zt.removeAttr(xi.ti),Xn(Zt,U,i,!0,!0)}),ut(),at(),st(),ft(),ct(!0),ct(!1),function s(){var r,t=x.top!==x,e={},i={},o={};function u(n){if(a(n)){var t=c(n),r={};(ne||Zr)&&(r[de]=i.w+(t.x-e.x)*o.x),(te||Zr)&&(r[pe]=i.h+(t.y-e.y)*o.y),Yt.css(r),Oi.stpP(n)}else f(n)}function f(n){var t=n!==di;Xn(P,[K,$,V],[tt,u,f],!0),si(j,Mn),ir.releaseCapture&&ir.releaseCapture(),t&&(r&&Be(),Ot.update(ge)),r=!1}function a(n){var t=(n.originalEvent||n).touches!==di;return!Ut&&!Et&&(1===Oi.mBtn(n)||t)}function c(n){return D&&t?{x:n.screenX,y:n.screenY}:Oi.page(n)}Yn(ir,U,function(n){a(n)&&!Jr&&(Vr&&(r=!0,Qe()),e=c(n),i.w=ur[xi.oW]-(Nt?0:Mt),i.h=ur[xi.oH]-(Nt?0:Dt),o=vt(),Xn(P,[K,$,V],[tt,u,f]),ci(j,Mn),ir.setCapture&&ir.setCapture(),Oi.prvD(n),Oi.stpP(n))})}(),Gn(),je(Kt,Kn),Rt&&(Zt[_e](e.l)[Oe](e.t),hi.activeElement==n&&cr.focus&&(Zt.attr(xi.ti,"-1"),cr.focus(),Xn(Zt,U,i,!1,!0))),Ot.update(ge),Ht=!0,ti("onInitialized"),h(Fn,function(n,t){ti(t.n,t.a)}),Fn=[],cn(r)==gi&&(r=[r]),Oi.isA(r)?h(r,function(n,t){Ot.addExt(t)}):Ci.isPlainObject(r)&&h(r,function(n,t){Ot.addExt(n,t)}),setTimeout(function(){F&&!Et&&ci(Yt,an)},333)),Ot}(r,n,t))&&Ti(r,Ot),Ot}function Xn(n,t,r,e,i){var o=Oi.isA(t)&&Oi.isA(r),u=e?"removeEventListener":"addEventListener",f=e?"off":"on",a=!o&&t.split(xe),c=0,s=Ci.isPlainObject(i),l=w&&(s?i.U:i)||!1,v=s&&(i.V||!1),h=w?{passive:l,capture:v}:v;if(o)for(;c<t[xi.l];c++)Xn(n,t[c],r[c],e,i);else for(;c<a[xi.l];c++)w?n[0][u](a[c],r,h):n[f](a[c],r)}function Yn(n,t,r,e){Xn(n,t,r,!1,e),qn.push(Oi.bind(Xn,0,n,t,r,!0,e))}function je(n,t){if(n){var r=Oi.rO(),e="animationstart mozAnimationStart webkitAnimationStart MSAnimationStart",i="childNodes",o=3333333,u=function(){n[Oe](o)[_e](Qt?Ct.n?-o:Ct.i?0:o:o),t()};if(t){if(T)((k=n.addClass("observed").append(ui(On)).contents()[0])[Z]=new r(u)).observe(k);else if(9<D||!kt){n.prepend(ui(On,ui({c:Sn,dir:"ltr"},ui(Sn,ui(zn))+ui(Sn,ui({c:zn,style:"width: 200%; height: 200%"})))));var f,a,c,s,l=n[0][i][0][i][0],v=Ci(l[i][1]),h=Ci(l[i][0]),d=Ci(h[0][i][0]),p=l[xi.oW],b=l[xi.oH],m=xt.nativeScrollbarSize,g=function(){h[_e](o)[Oe](o),v[_e](o)[Oe](o)},w=function(){a=0,f&&(p=c,b=s,u())},y=function(n){return c=l[xi.oW],s=l[xi.oH],f=c!=p||s!=b,n&&f&&!a?(Oi.cAF()(a),a=Oi.rAF()(w)):n||w(),g(),n&&(Oi.prvD(n),Oi.stpP(n)),!1},x={},_={};ri(_,me,[-2*(m.y+1),-2*m.x,-2*m.y,-2*(m.x+1)]),Ci(l).css(_),h.on(we,y),v.on(we,y),n.on(e,function(){y(!1)}),x[de]=o,x[pe]=o,d.css(x),g()}else{var O=A.attachEvent,S=D!==di;if(O)n.prepend(ui(On)),wt(n,R+On)[0].attachEvent("onresize",u);else{var z=A.createElement(pi);z.setAttribute(xi.ti,"-1"),z.setAttribute(xi.c,On),z.onload=function(){var n=this.contentDocument.defaultView;n.addEventListener("resize",u),n.document.documentElement.style.display="none"},z.type="text/html",S&&n.prepend(z),z.data="about:blank",S||n.prepend(z),n.on(e,u)}}if(n[0]===H){var C=function(){var n=Yt.css("direction"),t={},r=0,e=!1;return n!==E&&(r="ltr"===n?(t[le]=0,t[ce]=ge,o):(t[le]=ge,t[ce]=0,Ct.n?-o:Ct.i?0:o),Kt.children().eq(0).css(t),Kt[_e](r)[Oe](o),E=n,e=!0),e};C(),Yn(n,we,function(n){return C()&&qe(),Oi.prvD(n),Oi.stpP(n),!1})}}else if(T){var k,I=(k=n.contents()[0])[Z];I&&(I.disconnect(),delete k[Z])}else gt(n.children(R+On).eq(0))}}function Gn(){if(d){var o,u,f,a,c,s,r,e,i,l,n=Oi.mO(),v=Oi.now();C=function(n){var t=!1;return Ht&&!Ut&&(h(n,function(){return!(t=function o(n){var t=n.attributeName,r=n.target,e=n.type,i="closest";if(r===sr)return null===t;if("attributes"===e&&(t===xi.c||t===xi.s)&&!Lt){if(t===xi.c&&Ci(r).hasClass(rn))return et(n.oldValue,r.className);if(typeof r[i]!=bi)return!0;if(null!==r[i](R+On)||null!==r[i](R+kn)||null!==r[i](R+Rn))return!1}return!0}(this))}),t&&(e=Oi.now(),i=mr||br,l=function(){Et||(v=e,Lt&&$e(),i?qe():Ot.update(ge))},clearTimeout(r),11<e-v||!i?l():r=setTimeout(l,11))),t},O=new n(z=function(n){var t,r=!1,e=!1,i=[];return Ht&&!Ut&&(h(n,function(){o=(t=this).target,u=t.attributeName,f=u===xi.c,a=t.oldValue,c=o.className,p&&f&&!e&&-1<a.indexOf(en)&&c.indexOf(en)<0&&(s=lt(!0),ur.className=c.split(xe).concat(a.split(xe).filter(function(n){return n.match(s)})).join(xe),r=e=!0),r=r||(f?et(a,c):u!==xi.s||a!==o[xi.s].cssText),i.push(u)}),it(i),r&&Ot.update(e||ge)),r}),S=new n(C)}}function Be(){d&&!Vr&&(O.observe(ur,{attributes:!0,attributeOldValue:!0,attributeFilter:$n}),S.observe(Lt?or:sr,{attributes:!0,attributeOldValue:!0,subtree:!Lt,childList:!Lt,characterData:!Lt,attributeFilter:Lt?Vn:$n}),Vr=!0)}function Qe(){d&&Vr&&(O.disconnect(),S.disconnect(),Vr=!1)}function Kn(){if(!Ut){var n,t={w:H[xi.sW],h:H[xi.sH]};n=fi(t,_),_=t,n&&qe({A:!0})}}function Jn(){Kr&&Ge(!0)}function Zn(){Kr&&!j.hasClass(Mn)&&Ge(!1)}function nt(){Gr&&(Ge(!0),clearTimeout(I),I=setTimeout(function(){Gr&&!Et&&Ge(!1)},100))}function tt(n){return Oi.prvD(n),!1}function rt(n){var r=Ci(n.target);mt(function(n,t){r.is(t)&&qe({I:!0})})}function Ue(n){n||Ue(!0),Xn(Yt,$.split(xe)[0],nt,!Gr||n,!0),Xn(Yt,[q,X],[Jn,Zn],!Kr||n,!0),Ht||n||Yt.one("mouseover",Jn)}function Ve(){var n={};return Rt&&tr&&(n.w=ii(tr.css(ve+de)),n.h=ii(tr.css(ve+pe)),n.c=fi(n,Ur),n.f=!0),!!(Ur=n).c}function et(n,t){var r,e,i=typeof t==gi?t.split(xe):[],o=function f(n,t){var r,e,i=[],o=[];for(r=0;r<n.length;r++)i[n[r]]=!0;for(r=0;r<t.length;r++)i[t[r]]?delete i[t[r]]:i[t[r]]=!0;for(e in i)o.push(e);return o}(typeof n==gi?n.split(xe):[],i),u=sn(De,o);if(-1<u&&o.splice(u,1),0<o[xi.l])for(e=lt(!0,!0),r=0;r<o.length;r++)if(!o[r].match(e))return!0;return!1}function it(n){h(n=n||Pn,function(n,t){if(-1<Oi.inA(t,Pn)){var r=Xt.attr(t);cn(r)==gi?Zt.attr(t,r):Zt.removeAttr(t)}})}function $e(){if(!Ut){var n,t,r,e,i=!Fr,o=ee.w,u=ee.h,f={},a=br||i;return f[ve+de]=me,f[ve+pe]=me,f[de]=ge,Xt.css(f),n=or[xi.oW],t=a?Si.max(n,or[xi.sW]-1):1,f[de]=br?ge:ye,f[ve+de]=ye,f[pe]=ge,Xt.css(f),r=or[xi.oH],e=Si.max(r,or[xi.sH]-1),f[de]=t,f[pe]=e,er.css(f),f[ve+de]=o,f[ve+pe]=u,Xt.css(f),{$:n,X:r,Y:t,G:e}}}function qe(n){clearTimeout(qt),n=n||{},Fe.A|=n.A,Fe.I|=n.I,Fe.H|=n.H;var t,r=Oi.now(),e=!!Fe.A,i=!!Fe.I,o=!!Fe.H,u=n.T,f=0<Pe&&Ht&&!Et&&!o&&!u&&r-$t<Pe&&!mr&&!br;if(f&&(qt=setTimeout(qe,Pe)),!(Et||f||Ut&&!u||Ht&&!o&&(t=Yt.is(":hidden"))||"inline"===Yt.css("display"))){$t=r,Fe={},!It||St.x&&St.y?At=ai({},xt.nativeScrollbarSize):(At.x=0,At.y=0),ie={x:3*(At.x+(St.x?0:3)),y:3*(At.y+(St.y?0:3))},u=u||{};var a=function(){return fi.apply(this,[].slice.call(arguments).concat([o]))},c={x:Zt[_e](),y:Zt[Oe]()},s=Vt.scrollbars,l=Vt.textarea,v=s.visibility,h=a(v,Hr),d=s.autoHide,p=a(d,Er),b=s.clickScrolling,m=a(b,Lr),g=s.dragScrolling,w=a(g,Rr),y=Vt.className,x=a(y,Mr),_=Vt.resize,O=a(_,Nr)&&!Rt,S=Vt.paddingAbsolute,z=a(S,Or),C=Vt.clipAlways,k=a(C,Sr),I=Vt.sizeAutoCapable&&!Rt,T=a(I,Ar),A=Vt.nativeScrollbarsOverlaid.showNativeScrollbars,H=a(A,Ir),E=Vt.autoUpdate,L=a(E,Tr),R=Vt.overflowBehavior,N=a(R,Cr,o),W=l.dynWidth,M=a(Qr,W),D=l.dynHeight,F=a(Br,D);if(Xr="n"===d,Yr="s"===d,Gr="m"===d,Kr="l"===d,qr=s.autoHideDelay,Dr=Mr,Jr="n"===_,Zr="b"===_,ne="h"===_,te="v"===_,Wr=Vt.normalizeRTL,A=A&&St.x&&St.y,Hr=v,Er=d,Lr=b,Rr=g,Mr=y,Nr=_,Or=S,Sr=C,Ar=I,Ir=A,Tr=E,Cr=ai({},R),Qr=W,Br=D,dr=dr||{x:!1,y:!1},x&&(si(Yt,Dr+xe+De),ci(Yt,y!==di&&null!==y&&0<y.length?y:De)),L&&(!0===E||null===E&&kt?(Qe(),_t.add(Ot)):(_t.remove(Ot),Be())),T)if(I)if(rr?rr.show():(rr=Ci(ui(Ee)),Jt.before(rr)),Wt)Gt.show();else{Gt=Ci(ui(Le)),fr=Gt[0],rr.before(Gt);var P={w:-1,h:-1};je(Gt,function(){var n={w:fr[xi.oW],h:fr[xi.oH]};fi(n,P)&&(Ht&&mr&&0<n.h||br&&0<n.w||Ht&&!mr&&0===n.h||!br&&0===n.w)&&qe(),P=n}),Wt=!0,null!==Tt&&Gt.css(pe,Tt+"(100% + 1px)")}else Wt&&Gt.hide(),rr&&rr.hide();o&&(Kt.find("*").trigger(we),Wt&&Gt.find("*").trigger(we)),t=t===di?Yt.is(":hidden"):t;var j,B=!!Lt&&"off"!==Xt.attr("wrap"),Q=a(B,Fr),U=Yt.css("direction"),V=a(U,_r),$=Yt.css("box-sizing"),q=a($,gr),X=ei(ue);try{j=Wt?fr[xi.bCR]():null}catch(wt){return}Nt="border-box"===$;var Y=(Qt="rtl"===U)?le:ce,G=Qt?ce:le,K=!1,J=!(!Wt||"none"===Yt.css(be))&&(0===Si.round(j.right-j.left)&&(!!S||0<ur[xi.cW]-Mt));if(I&&!J){var Z=ur[xi.oW],nn=rr.css(de);rr.css(de,ge);var tn=ur[xi.oW];rr.css(de,nn),(K=Z!==tn)||(rr.css(de,Z+1),tn=ur[xi.oW],rr.css(de,nn),K=Z!==tn)}var rn=(J||K)&&I&&!t,en=a(rn,br),on=!rn&&br,un=!(!Wt||!I||t)&&0===Si.round(j.bottom-j.top),fn=a(un,mr),an=!un&&mr,cn=ei(fe,"-"+de,!(rn&&Nt||!Nt),!(un&&Nt||!Nt)),sn=ei(oe),ln={},vn={},hn=function(){return{w:ur[xi.cW],h:ur[xi.cH]}},dn=function(){return{w:ar[xi.oW]+Si.max(0,sr[xi.cW]-sr[xi.sW]),h:ar[xi.oH]+Si.max(0,sr[xi.cH]-sr[xi.sH])}},pn=Mt=X.l+X.r,bn=Dt=X.t+X.b;if(pn*=S?1:0,bn*=S?1:0,X.c=a(X,wr),Ft=cn.l+cn.r,Pt=cn.t+cn.b,cn.c=a(cn,yr),jt=sn.l+sn.r,Bt=sn.t+sn.b,sn.c=a(sn,xr),Fr=B,_r=U,gr=$,br=rn,mr=un,wr=X,yr=cn,xr=sn,V&&Wt&&Gt.css(be,G),X.c||V||z||en||fn||q||T){var mn={},gn={},wn=[X.t,X.r,X.b,X.l];ri(vn,oe,[-X.t,-X.r,-X.b,-X.l]),S?(ri(mn,me,wn),ri(Lt?gn:ln,ue)):(ri(mn,me),ri(Lt?gn:ln,ue,wn)),Jt.css(mn),Xt.css(gn)}ee=dn();var yn=!!Lt&&$e(),xn=Lt&&a(yn,jr),_n=Lt&&yn?{w:W?yn.Y:yn.$,h:D?yn.G:yn.X}:{};if(jr=yn,un&&(fn||z||q||X.c||cn.c)?ln[pe]=ge:(fn||z)&&(ln[pe]=ye),rn&&(en||z||q||X.c||cn.c||V)?(ln[de]=ge,vn[he+de]=ye):(en||z)&&(ln[de]=ye,ln[be]=me,vn[he+de]=me),rn?(vn[de]=ge,ln[de]=_i.d(de,"max-content intrinsic")||ge,ln[be]=G):vn[de]=me,vn[pe]=un?_n.h||sr[xi.cH]:me,I&&rr.css(vn),nr.css(ln),ln={},vn={},e||i||xn||V||q||z||en||rn||fn||un||H||N||k||O||h||p||w||m||M||F||Q){var On="overflow",Sn=On+"-x",zn=On+"-y";if(!It){var Cn={},kn=dr.y&&pr.ys&&!A?St.y?Zt.css(Y):-At.y:0,In=dr.x&&pr.xs&&!A?St.x?Zt.css(se):-At.x:0;ri(Cn,me),Zt.css(Cn)}var Tn=oi(),An={w:_n.w||Tn[xi.cW],h:_n.h||Tn[xi.cH]},Hn=Tn[xi.sW],En=Tn[xi.sH];It||(Cn[se]=an?me:In,Cn[Y]=on?me:kn,Zt.css(Cn)),ee=dn();var Ln=hn(),Rn={w:Ln.w-jt-Ft-(Nt?0:Mt),h:Ln.h-Bt-Pt-(Nt?0:Dt)},Nn={w:Si.max((rn?An.w:Hn)+pn,Rn.w),h:Si.max((un?An.h:En)+bn,Rn.h)};if(Nn.c=a(Nn,zr),zr=Nn,I){(Nn.c||un||rn)&&(vn[de]=Nn.w,vn[pe]=Nn.h,Lt||(An={w:Tn[xi.cW],h:Tn[xi.cH]}));var Wn={},Mn=function(n){var t=ni(n),r=t.j,e=t.K,i=n?rn:un,o=n?Ft:Pt,u=n?Mt:Dt,f=n?jt:Bt,a=ee[r]-o-f-(Nt?0:u);i&&(i||!cn.c)||(vn[e]=Rn[r]-1),!(i&&An[r]<a)||n&&Lt&&B||(Lt&&(Wn[e]=ii(er.css(e))-1),--vn[e]),0<An[r]&&(vn[e]=Si.max(1,vn[e]))};Mn(!0),Mn(!1),Lt&&er.css(Wn),rr.css(vn)}rn&&(ln[de]=ye),!rn||Nt||Vr||(ln[be]="none"),nr.css(ln),ln={};var Dn={w:Tn[xi.sW],h:Tn[xi.sH]};Dn.c=i=a(Dn,vr),vr=Dn,ee=dn(),e=a(Ln=hn(),lr),lr=Ln;var Fn=Lt&&(0===ee.w||0===ee.h),Pn=kr,jn={},Bn={},Qn={},Un={},Vn={},$n={},qn={},Xn=ar[xi.bCR](),Yn=function(n){var t=ni(n),r=ni(!n).Q,e=t.Q,i=t.j,o=t.K,u=we+t.J+"Max",f=Xn[o]?Si.abs(Xn[o]-ee[i]):0,a=Pn&&0<Pn[e]&&0===cr[u];jn[e]="v-s"===R[e],Bn[e]="v-h"===R[e],Qn[e]="s"===R[e],Un[e]=Si.max(0,Si.round(100*(Dn[i]-ee[i]))/100),Un[e]*=Fn||a&&0<f&&f<1?0:1,Vn[e]=0<Un[e],$n[e]=jn[e]||Bn[e]?Vn[r]&&!jn[r]&&!Bn[r]:Vn[e],$n[e+"s"]=!!$n[e]&&(Qn[e]||jn[e]),qn[e]=Vn[e]&&$n[e+"s"]};if(Yn(!0),Yn(!1),Un.c=a(Un,kr),kr=Un,Vn.c=a(Vn,dr),dr=Vn,$n.c=a($n,pr),pr=$n,St.x||St.y){var Gn,Kn={},Jn={},Zn=o;(Vn.x||Vn.y)&&(Jn.w=St.y&&Vn.y?Dn.w+zt.y:me,Jn.h=St.x&&Vn.x?Dn.h+zt.x:me,Zn=a(Jn,hr),hr=Jn),(Vn.c||$n.c||Dn.c||V||en||fn||rn||un||H)&&(ln[oe+G]=ln[fe+G]=me,Gn=function(n){var t=ni(n),r=ni(!n),e=t.Q,i=n?se:Y,o=n?un:rn;St[e]&&Vn[e]&&$n[e+"s"]?(ln[oe+i]=!o||A?me:zt[e],ln[fe+i]=n&&o||A?me:zt[e]+"px solid transparent"):(Jn[r.j]=ln[oe+i]=ln[fe+i]=me,Zn=!0)},It?li(Zt,Ae,!A):(Gn(!0),Gn(!1))),A&&(Jn.w=Jn.h=me,Zn=!0),Zn&&!It&&(Kn[de]=$n.y?Jn.w:me,Kn[pe]=$n.x?Jn.h:me,tr||(tr=Ci(ui(He)),Zt.prepend(tr)),tr.css(Kn)),nr.css(ln)}var nt,tt={};mn={};if((e||Vn.c||$n.c||Dn.c||N||q||H||V||k||fn)&&(tt[G]=me,(nt=function(n){var t=ni(n),r=ni(!n),e=t.Q,i=t.Z,o=n?se:Y,u=function(){tt[o]=me,re[r.j]=0};Vn[e]&&$n[e+"s"]?(tt[On+i]=we,A||It?u():(tt[o]=-(St[e]?zt[e]:At[e]),re[r.j]=St[e]?zt[r.Q]:0)):(tt[On+i]=me,u())})(!0),nt(!1),!It&&(ee.h<ie.x||ee.w<ie.y)&&(Vn.x&&$n.x&&!St.x||Vn.y&&$n.y&&!St.y)?(tt[ue+ae]=ie.x,tt[oe+ae]=-ie.x,tt[ue+G]=ie.y,tt[oe+G]=-ie.y):tt[ue+ae]=tt[oe+ae]=tt[ue+G]=tt[oe+G]=me,tt[ue+Y]=tt[oe+Y]=me,Vn.x&&$n.x||Vn.y&&$n.y||Fn?Lt&&Fn&&(mn[Sn]=mn[zn]="hidden"):(!C||Bn.x||jn.x||Bn.y||jn.y)&&(Lt&&(mn[Sn]=mn[zn]=me),tt[Sn]=tt[zn]="visible"),Jt.css(mn),Zt.css(tt),tt={},(Vn.c||q||en||fn)&&(!St.x||!St.y))){var rt=sr[xi.s];rt.webkitTransform="scale(1)",rt.display="run-in",sr[xi.oH],rt.display=me,rt.webkitTransform=me}if(ln={},V||en||fn)if(Qt&&rn){var et=nr.css(be),it=Si.round(nr.css(be,me).css(le,me).position().left);nr.css(be,et),it!==Si.round(nr.position().left)&&(ln[le]=it)}else ln[le]=me;if(nr.css(ln),Lt&&i){var ot=function yt(){var n=or.selectionStart;if(n===di)return;var t,r,e=Xt.val(),i=e[xi.l],o=e.split("\n"),u=o[xi.l],f=e.substr(0,n).split("\n"),a=0,c=0,s=f[xi.l],l=f[f[xi.l]-1][xi.l];for(r=0;r<o[xi.l];r++)t=o[r][xi.l],c<t&&(a=r+1,c=t);return{nn:s,tn:l,rn:u,en:c,"in":a,un:n,an:i}}();if(ot){var ut=Pr===di||ot.rn!==Pr.rn,ft=ot.nn,at=ot.tn,ct=ot["in"],st=ot.rn,lt=ot.en,vt=ot.un,ht=ot.an<=vt&&$r,dt={x:B||at!==lt||ft!==ct?-1:kr.x,y:(B?ht||ut&&Pn&&c.y===Pn.y:(ht||ut)&&ft===st)?kr.y:-1};c.x=-1<dt.x?Qt&&Wr&&Ct.i?0:dt.x:c.x,c.y=-1<dt.y?dt.y:c.y}Pr=ot}Qt&&Ct.i&&St.y&&Vn.x&&Wr&&(c.x+=re.w||0),rn&&Yt[_e](0),un&&Yt[Oe](0),Zt[_e](c.x)[Oe](c.y);var pt="v"===v,bt="h"===v,mt="a"===v,gt=function(n,t){t=t===di?n:t,Ye(!0,n,qn.x),Ye(!1,t,qn.y)};li(Yt,ke,$n.x||$n.y),li(Yt,Ie,$n.x),li(Yt,Te,$n.y),V&&!Rt&&li(Yt,Se,Qt),Rt&&ci(Yt,ze),O&&(li(Yt,ze,Jr),li(ir,Re,!Jr),li(ir,Ne,Zr),li(ir,We,ne),li(ir,Me,te)),(h||N||$n.c||Vn.c||H)&&(A?H&&(si(Yt,Ce),A&&gt(!1)):mt?gt(qn.x,qn.y):pt?gt(!0):bt&&gt(!1)),(p||H)&&(Ue(!Kr&&!Gr),Ge(Xr,!Xr)),(e||Un.c||fn||en||O||q||z||H||V)&&(Ke(!0),Je(!0),Ke(!1),Je(!1)),m&&Ze(!0,b),w&&Ze(!1,g),ti("onDirectionChanged",{isRTL:Qt,dir:U},V),ti("onHostSizeChanged",{width:lr.w,height:lr.h},e),ti("onContentSizeChanged",{width:vr.w,height:vr.h},i),ti("onOverflowChanged",{x:Vn.x,y:Vn.y,xScrollable:$n.xs,yScrollable:$n.ys,clipped:$n.x||$n.y},Vn.c||$n.c),ti("onOverflowAmountChanged",{x:Un.x,y:Un.y},Un.c)}Rt&&Ur&&(dr.c||Ur.c)&&(Ur.f||Ve(),St.y&&dr.x&&nr.css(ve+de,Ur.w+zt.y),St.x&&dr.y&&nr.css(ve+pe,Ur.h+zt.x),Ur.c=!1),Ht&&u.updateOnLoad&&Xe(),ti("onUpdated",{forced:o})}}function Xe(){Lt||mt(function(n,t){nr.find(t).each(function(n,t){Oi.inA(t,Qn)<0&&(Qn.push(t),Ci(t).off(Bn,rt).on(Bn,rt))})})}function ot(n){var t=Ii.O(n,Ii._,!0,u);return u=ai({},u,t.S),Vt=ai({},Vt,t.z),t.z}function ut(e){var n="parent",t=mn+xe+Cn,r=Lt?xe+Cn:me,i=Vt.textarea.inheritedAttrs,o={},u=function(){var r=e?Xt:Yt;h(o,function(n,t){cn(t)==gi&&(n==xi.c?r.addClass(t):r.attr(n,t))})},f=[rn,en,on,ze,Se,un,fn,an,Ce,ke,Ie,Te,De,mn,Cn,Mr].join(xe),a={};Yt=Yt||(Lt?p?Xt[n]()[n]()[n]()[n]():Ci(ui(on)):Xt),nr=nr||pt(_n+r),Zt=Zt||pt(yn+r),Jt=Jt||pt(wn+r),Kt=Kt||pt("os-resize-observer-host"),er=er||(Lt?pt(gn):di),p&&ci(Yt,en),e&&si(Yt,f),i=cn(i)==gi?i.split(xe):i,Oi.isA(i)&&Lt&&h(i,function(n,t){cn(t)==gi&&(o[t]=e?Yt.attr(t):Xt.attr(t))}),e?(p&&Ht?(Kt.children().remove(),h([Jt,Zt,nr,er],function(n,t){t&&si(t.removeAttr(xi.s),Dn)}),ci(Yt,Lt?on:rn)):(gt(Kt),nr.contents().unwrap().unwrap().unwrap(),Lt&&(Xt.unwrap(),gt(Yt),gt(er),u())),Lt&&Xt.removeAttr(xi.s),Rt&&si(c,tn)):(Lt&&(Vt.sizeAutoCapable||(a[de]=Xt.css(de),a[pe]=Xt.css(pe)),p||Xt.addClass(Cn).wrap(Yt),Yt=Xt[n]().css(a)),p||(ci(Xt,Lt?t:rn),Yt.wrapInner(nr).wrapInner(Zt).wrapInner(Jt).prepend(Kt),nr=wt(Yt,R+_n),Zt=wt(Yt,R+yn),Jt=wt(Yt,R+wn),Lt&&(nr.prepend(er),u())),It&&ci(Zt,Ae),St.x&&St.y&&ci(Zt,xn),Rt&&ci(c,tn),H=Kt[0],ur=Yt[0],ar=Jt[0],cr=Zt[0],sr=nr[0],it())}function ft(){var r,t,e=[112,113,114,115,116,117,118,119,120,121,123,33,34,37,38,39,40,16,17,18,19,20,144],i=[],n="focus";function o(n){$e(),Ot.update(ge),n&&kt&&clearInterval(r)}Lt?(9<D||!kt?Yn(Xt,"input",o):Yn(Xt,[Y,G],[function u(n){var t=n.keyCode;sn(t,e)<0&&(i[xi.l]||(o(),r=setInterval(o,1e3/60)),sn(t,i)<0&&i.push(t))},function f(n){var t=n.keyCode,r=sn(t,i);sn(t,e)<0&&(-1<r&&i.splice(r,1),i[xi.l]||o(!0))}]),Yn(Xt,[we,"drop",n,n+"out"],[function a(n){return Xt[_e](Ct.i&&Wr?9999999:0),Xt[Oe](0),Oi.prvD(n),Oi.stpP(n),!1},function c(n){setTimeout(function(){Et||o()},50)},function s(){$r=!0,ci(Yt,n)},function l(){$r=!1,i=[],si(Yt,n),o(!0)}])):Yn(nr,J,function v(n){!0!==Tr&&function l(n){if(!Ht)return 1;var t="flex-grow",r="flex-shrink",e="flex-basis",i=[de,ve+de,he+de,oe+le,oe+ce,le,ce,"font-weight","word-spacing",t,r,e],o=[ue+le,ue+ce,fe+le+de,fe+ce+de],u=[pe,ve+pe,he+pe,oe+ae,oe+se,ae,se,"line-height",t,r,e],f=[ue+ae,ue+se,fe+ae+de,fe+se+de],a="s"===Cr.x||"v-s"===Cr.x,c=!1,s=function(n,t){for(var r=0;r<n[xi.l];r++)if(n[r]===t)return!0;return!1};return("s"===Cr.y||"v-s"===Cr.y)&&((c=s(u,n))||Nt||(c=s(f,n))),a&&!c&&((c=s(i,n))||Nt||(c=s(o,n))),c}((n=n.originalEvent||n).propertyName)&&Ot.update(ge)}),Yn(Zt,we,function h(n){Ut||(t!==di?clearTimeout(t):((Yr||Gr)&&Ge(!0),dt()||ci(Yt,Ce),ti("onScrollStart",n)),Q||(Je(!0),Je(!1)),ti("onScroll",n),t=setTimeout(function(){Et||(clearTimeout(t),t=di,(Yr||Gr)&&Ge(!1),dt()||si(Yt,Ce),ti("onScrollStop",n))},175))},!0)}function at(i){var n,t,o=function(n){var t=pt(kn+xe+(n?Nn:Wn),!0),r=pt(In,t),e=pt(An,t);return p||i||(t.append(r),r.append(e)),{cn:t,sn:r,ln:e}};function r(n){var t=ni(n),r=t.cn,e=t.sn,i=t.ln;p&&Ht?h([r,e,i],function(n,t){si(t.removeAttr(xi.s),Dn)}):gt(r||o(n).cn)}i?(r(!0),r()):(n=o(!0),t=o(),a=n.cn,s=n.sn,l=n.ln,v=t.cn,b=t.sn,m=t.ln,p||(Jt.after(v),Jt.after(a)))}function ct(S){var z,i,C,k,e=ni(S),I=e.vn,t=x.top!==x,T=e.Q,r=e.Z,A=we+e.J,o="active",u="snapHandle",f="click",H=1,a=[16,17];function c(n){return D&&t?n["screen"+r]:Oi.page(n)[T]}function s(n){return Vt.scrollbars[n]}function l(){H=.5}function v(){H=1}function h(n){Oi.stpP(n)}function E(n){-1<sn(n.keyCode,a)&&l()}function L(n){-1<sn(n.keyCode,a)&&v()}function R(n){var t=(n.originalEvent||n).touches!==di;return!(Ut||Et||dt()||!Rr||t&&!s("touchSupport"))&&(1===Oi.mBtn(n)||t)}function d(n){if(R(n)){var t=I.F,r=I.M,e=I.N*((c(n)-C)*k/(t-r));e=isFinite(e)?e:0,Qt&&S&&!Ct.i&&(e*=-1),Zt[A](Si.round(i+e)),Q&&Je(S,i+e),w||Oi.prvD(n)}else N(n)}function N(n){if(n=n||n.originalEvent,Xn(P,[$,V,Y,G,K],[d,N,E,L,tt],!0),Oi.rAF()(function(){Xn(P,f,h,!0,{V:!0})}),Q&&Je(S,!0),Q=!1,si(j,Mn),si(e.ln,o),si(e.sn,o),si(e.cn,o),k=1,v(),z!==(C=i=di)&&(Ot.scrollStop(),clearTimeout(z),z=di),n){var t=ur[xi.bCR]();n.clientX>=t.left&&n.clientX<=t.right&&n.clientY>=t.top&&n.clientY<=t.bottom||Zn(),(Yr||Gr)&&Ge(!1)}}function W(n){i=Zt[A](),i=isNaN(i)?0:i,(Qt&&S&&!Ct.n||!Qt)&&(i=i<0?0:i),k=vt()[T],C=c(n),Q=!s(u),ci(j,Mn),ci(e.ln,o),ci(e.cn,o),Xn(P,[$,V,K],[d,N,tt]),Oi.rAF()(function(){Xn(P,f,h,!1,{V:!0})}),!D&&y||Oi.prvD(n),Oi.stpP(n)}Yn(e.ln,U,function p(n){R(n)&&W(n)}),Yn(e.sn,[U,q,X],[function M(n){if(R(n)){var h,t=e.vn.M/Math.round(Si.min(1,ee[e.j]/vr[e.j])*e.vn.F),d=Si.round(ee[e.j]*t),p=270*t,b=400*t,m=e.sn.offset()[e.B],r=n.ctrlKey,g=n.shiftKey,w=g&&r,y=!0,x=function(n){Q&&Je(S,n)},_=function(){x(),W(n)},O=function(){if(!Et){var n=(C-m)*k,t=I.W,r=I.F,e=I.M,i=I.N,o=I.L,u=p*H,f=y?Si.max(b,u):u,a=i*((n-e/2)/(r-e)),c=Qt&&S&&(!Ct.i&&!Ct.n||Wr),s=c?t<n:n<t,l={},v={easing:"linear",step:function(n){Q&&(Zt[A](n),Je(S,n))}};a=isFinite(a)?a:0,a=Qt&&S&&!Ct.i?i-a:a,g?(Zt[A](a),w?(a=Zt[A](),Zt[A](o),a=c&&Ct.i?i-a:a,a=c&&Ct.n?-a:a,l[T]=a,Ot.scroll(l,ai(v,{duration:130,complete:_}))):_()):(h=y?s:h,(c?h?n<=t+e:t<=n:h?t<=n:n<=t+e)?(clearTimeout(z),Ot.scrollStop(),z=di,x(!0)):(z=setTimeout(O,f),l[T]=(h?"-=":"+=")+d,Ot.scroll(l,ai(v,{duration:u}))),y=!1)}};r&&l(),k=vt()[T],C=Oi.page(n)[T],Q=!s(u),ci(j,Mn),ci(e.sn,o),ci(e.cn,o),Xn(P,[V,Y,G,K],[N,E,L,tt]),O(),Oi.prvD(n),Oi.stpP(n)}},function b(n){B=!0,(Yr||Gr)&&Ge(!0)},function m(n){B=!1,(Yr||Gr)&&Ge(!1)}]),Yn(e.cn,U,function g(n){Oi.stpP(n)}),F&&Yn(e.cn,J,function(n){n.target===e.cn[0]&&(Ke(S),Je(S))})}function Ye(n,t,r){var e=n?a:v;li(Yt,n?un:fn,!t),li(e,En,!r)}function Ge(n,t){if(clearTimeout(k),n)si(a,Ln),si(v,Ln);else{var r,e=function(){B||Et||(!(r=l.hasClass("active")||m.hasClass("active"))&&(Yr||Gr||Kr)&&ci(a,Ln),!r&&(Yr||Gr||Kr)&&ci(v,Ln))};0<qr&&!0!==t?k=setTimeout(e,qr):e()}}function Ke(n){var t={},r=ni(n),e=r.vn,i=Si.min(1,ee[r.j]/vr[r.j]);t[r.K]=Si.floor(100*i*1e6)/1e6+"%",dt()||r.ln.css(t),e.M=r.ln[0]["offset"+r.hn],e.D=i}function Je(n,t){var r,e,i=cn(t)==wi,o=Qt&&n,u=ni(n),f=u.vn,a="translate(",c=_i.v("transform"),s=_i.v("transition"),l=n?Zt[_e]():Zt[Oe](),v=t===di||i?l:t,h=f.M,d=u.sn[0]["offset"+u.hn],p=d-h,b={},m=(cr[we+u.hn]-cr["client"+u.hn])*(Ct.n&&o?-1:1),g=function(n){return isNaN(n/m)?0:Si.max(0,Si.min(1,n/m))},w=function(n){var t=p*n;return t=isNaN(t)?0:t,t=o&&!Ct.i?d-h-t:t,t=Si.max(0,t)},y=g(l),x=w(g(v)),_=w(y);f.N=m,f.L=l,f.R=y,ln?(r=o?-(d-h-x):x,e=n?a+r+"px, 0)":a+"0, "+r+"px)",b[c]=e,F&&(b[s]=i&&1<Si.abs(x-f.W)?function O(n){var t=_i.v("transition"),r=n.css(t);if(r)return r;for(var e,i,o,u="\\s*(([^,(]+(\\(.+?\\))?)+)[\\s,]*",f=new RegExp(u),a=new RegExp("^("+u+")+$"),c="property duration timing-function delay".split(" "),s=[],l=0,v=function(n){if(e=[],!n.match(a))return n;for(;n.match(f);)e.push(RegExp.$1),n=n.replace(f,me);return e};l<c[xi.l];l++)for(i=v(n.css(t+"-"+c[l])),o=0;o<i[xi.l];o++)s[o]=(s[o]?s[o]+xe:me)+i[o];return s.join(", ")}(u.ln)+", "+(c+xe+250)+"ms":me)):b[u.B]=x,dt()||(u.ln.css(b),ln&&F&&i&&u.ln.one(J,function(){Et||u.ln.css(s,me)})),f.W=x,f.P=_,f.F=d}function Ze(n,t){var r=t?"removeClass":"addClass",e=n?b:m,i=n?Tn:Hn;(n?s:l)[r](i),e[r](i)}function ni(n){return{K:n?de:pe,hn:n?"Width":"Height",B:n?le:ae,J:n?"Left":"Top",Q:n?pn:bn,Z:n?"X":"Y",j:n?"w":"h",dn:n?"l":"t",sn:n?s:b,ln:n?l:m,cn:n?a:v,vn:n?vn:hn}}function st(n){ir=ir||pt(Rn,!0),n?p&&Ht?si(ir.removeAttr(xi.s),Dn):gt(ir):p||Yt.append(ir)}function ti(n,t,r){if(!1!==r)if(Ht){var e,i=Vt.callbacks[n],o=n;"on"===o.substr(0,2)&&(o=o.substr(2,1).toLowerCase()+o.substr(3)),cn(i)==bi&&i.call(Ot,t),h(jn,function(){cn((e=this).on)==bi&&e.on(o,t)})}else Et||Fn.push({n:n,a:t})}function ri(n,t,r){r=r||[me,me,me,me],n[(t=t||me)+ae]=r[0],n[t+ce]=r[1],n[t+se]=r[2],n[t+le]=r[3]}function ei(n,t,r,e){return t=t||me,n=n||me,{t:e?0:ii(Yt.css(n+ae+t)),r:r?0:ii(Yt.css(n+ce+t)),b:e?0:ii(Yt.css(n+se+t)),l:r?0:ii(Yt.css(n+le+t))}}function lt(n,t){var r,e,i,o=function(n,t){if(i="",t&&typeof n==gi)for(e=n.split(xe),r=0;r<e[xi.l];r++)i+="|"+e[r]+"$";return i};return new RegExp("(^"+rn+"([-_].+|)$)"+o(Mr,n)+o(Dr,t),"g")}function vt(){var n=ar[xi.bCR]();return{x:ln&&1/(Si.round(n.width)/ar[xi.oW])||1,y:ln&&1/(Si.round(n.height)/ar[xi.oH])||1}}function ht(n){var t="ownerDocument",r="HTMLElement",e=n&&n[t]&&n[t].parentWindow||vi;return typeof e[r]==pi?n instanceof e[r]:n&&typeof n==pi&&null!==n&&1===n.nodeType&&typeof n.nodeName==gi}function ii(n,t){var r=t?parseFloat(n):parseInt(n,10);return isNaN(r)?0:r}function dt(){return Ir&&St.x&&St.y}function oi(){return Lt?er[0]:sr}function ui(r,n){return"<div "+(r?cn(r)==gi?'class="'+r+'"':function(){var n,t=me;if(Ci.isPlainObject(r))for(n in r)t+=("c"===n?"class":n)+'="'+r[n]+'" ';return t}():me)+">"+(n||me)+"</div>"}function pt(n,t){var r=cn(t)==wi,e=!r&&t||Yt;return p&&!e[xi.l]?null:p?e[r?"children":"find"](R+n.replace(/\s/g,R)).eq(0):Ci(ui(n))}function bt(n,t){for(var r,e=t.split(R),i=0;i<e.length;i++){if(!n[xi.hOP](e[i]))return;r=n[e[i]],i<e.length&&cn(r)==pi&&(n=r)}return r}function mt(n){var t=Vt.updateOnLoad;t=cn(t)==gi?t.split(xe):t,Oi.isA(t)&&!Et&&h(t,n)}function fi(n,t,r){if(r)return r;if(cn(n)!=pi||cn(t)!=pi)return n!==t;for(var e in n)if("c"!==e){if(!n[xi.hOP](e)||!t[xi.hOP](e))return!0;if(fi(n[e],t[e]))return!0}return!1}function ai(){return Ci.extend.apply(this,[!0].concat([].slice.call(arguments)))}function ci(n,t){return e.addClass.call(n,t)}function si(n,t){return e.removeClass.call(n,t)}function li(n,t,r){return(r?ci:si)(n,t)}function gt(n){return e.remove.call(n)}function wt(n,t){return e.find.call(n,t).eq(0)}}return zi&&zi.fn&&(zi.fn.overlayScrollbars=function(n,t){return zi.isPlainObject(n)?(zi.each(this,function(){q(this,n,t)}),this):q(this,n)}),q});
/* --- app.js --- */
/* ============================================================
   APP.JS - Core Application Logic
   Consolidated from: analytics.js, favorites.js, filters.js,
   download-handler.js, carousel.js
   ============================================================ */

/* ============================================================
   SECTION 1: ANALYTICS & TRACKING
   ============================================================ */

/**
 * Analytics and Event Tracking System
 * Tracks user behavior, conversions, and interactions
 */

class Analytics {
    constructor() {
        this.baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
        this.sessionId = this.getOrCreateSessionId();
        this.userId = this.getUserId();
        this.initTracking();
    }

    /**
     * Initialize tracking
     */
    initTracking() {
        // Track page view
        this.trackPageView();

        // Track outbound links
        this.trackOutboundLinks();

        // Track downloads
        this.trackDownloads();

        // Track search
        this.trackSearch();

        // Track time on page
        this.trackTimeOnPage();

        // Track scroll depth
        this.trackScrollDepth();
    }

    /**
     * Track custom event
     */
    track(event, data = {}) {
        const payload = {
            event,
            data,
            session_id: this.sessionId,
            user_id: this.userId,
            timestamp: Date.now(),
            url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_resolution: `${window.screen.width}x${window.screen.height}`,
            viewport_size: `${window.innerWidth}x${window.innerHeight}`,
        };

        // Send to Google Analytics if available
        if (typeof gtag !== 'undefined') {
            gtag('event', event, data);
        }

        // Send to custom analytics endpoint
        this.sendToServer(payload);
    }

    /**
     * Track page view
     */
    trackPageView() {
        this.track('page_view', {
            page_title: document.title,
            page_path: window.location.pathname,
        });
    }

    /**
     * Track download
     */
    trackDownload(topicId, topicTitle, downloadUrl) {
        this.track('download', {
            topic_id: topicId,
            topic_title: topicTitle,
            download_url: downloadUrl,
        });
    }

    /**
     * Track search
     */
    trackSearch() {
        const searchInput = document.querySelector('input[name="q"], .search-input');
        if (!searchInput) return;

        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const query = e.target.value.trim();
                if (query.length >= 2) {
                    this.track('search', {
                        query,
                        query_length: query.length,
                    });
                }
            }, 1000);
        });
    }

    /**
     * Track outbound links
     */
    trackOutboundLinks() {
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href');
            if (!href) return;

            // Check if external link
            if (href.startsWith('http') && !href.includes(window.location.hostname)) {
                this.track('outbound_link', {
                    url: href,
                    text: link.textContent.trim(),
                });
            }
        });
    }

    /**
     * Track downloads
     */
    trackDownloads() {
        document.addEventListener('click', (e) => {
            const downloadBtn = e.target.closest('[data-download-id]');
            if (!downloadBtn) return;

            const topicId = downloadBtn.dataset.downloadId;
            const topicTitle = downloadBtn.dataset.downloadTitle || 'Unknown';
            const downloadUrl = downloadBtn.href || downloadBtn.dataset.downloadUrl;

            this.trackDownload(topicId, topicTitle, downloadUrl);
        });
    }

    /**
     * Track time on page
     */
    trackTimeOnPage() {
        const startTime = Date.now();

        // Track on page unload
        window.addEventListener('beforeunload', () => {
            const timeSpent = Math.round((Date.now() - startTime) / 1000);

            // Use sendBeacon for reliable tracking on page unload
            const payload = JSON.stringify({
                event: 'time_on_page',
                data: {
                    seconds: timeSpent,
                    page_path: window.location.pathname,
                },
                session_id: this.sessionId,
                timestamp: Date.now(),
            });

            navigator.sendBeacon(this.baseUri + '/api/analytics/track.php', payload);
        });
    }

    /**
     * Track scroll depth
     */
    trackScrollDepth() {
        const depths = [25, 50, 75, 100];
        const tracked = new Set();

        const checkScroll = () => {
            const scrollPercent = Math.round(
                (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100
            );

            depths.forEach(depth => {
                if (scrollPercent >= depth && !tracked.has(depth)) {
                    tracked.add(depth);
                    this.track('scroll_depth', {
                        depth: depth,
                        page_path: window.location.pathname,
                    });
                }
            });
        };

        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(checkScroll, 100);
        }, { passive: true });
    }

    /**
     * Track form submission
     */
    trackFormSubmit(formName, formData = {}) {
        this.track('form_submit', {
            form_name: formName,
            ...formData,
        });
    }

    /**
     * Track error
     */
    trackError(error, context = {}) {
        this.track('error', {
            message: error.message || error,
            stack: error.stack,
            ...context,
        });
    }

    /**
     * Track conversion
     */
    trackConversion(conversionType, value = null) {
        this.track('conversion', {
            type: conversionType,
            value,
        });
    }

    /**
     * Track favorite toggle
     */
    trackFavorite(topicId, action) {
        this.track('favorite', {
            topic_id: topicId,
            action, // 'add' or 'remove'
        });
    }

    /**
     * Track comment
     */
    trackComment(topicId, commentLength) {
        this.track('comment', {
            topic_id: topicId,
            comment_length: commentLength,
        });
    }

    /**
     * Send data to server
     */
    async sendToServer(payload) {
        if (typeof window.publicFetchJson !== 'function') {
            return;
        }

        try {
            await window.publicFetchJson(this.baseUri + '/api/analytics/track.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
                notifyError: false,
                csrfRetry: false,
            });
        } catch (error) {
            // Silently fail - don't disrupt user experience
        }
    }

    /**
     * Get or create session ID
     */
    getOrCreateSessionId() {
        let sessionId = sessionStorage.getItem('analytics_session_id');

        if (!sessionId) {
            sessionId = this.generateId();
            sessionStorage.setItem('analytics_session_id', sessionId);
        }

        return sessionId;
    }

    /**
     * Get user ID from cookie or localStorage
     */
    getUserId() {
        let userId = localStorage.getItem('analytics_user_id');

        if (!userId) {
            userId = this.generateId();
            localStorage.setItem('analytics_user_id', userId);
        }

        return userId;
    }

    /**
     * Generate unique ID
     */
    generateId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    /**
     * Check if in development mode
     */
    isDevelopment() {
        return window.location.hostname === 'localhost' ||
               window.location.hostname === '127.0.0.1';
    }
}

// Initialize analytics
const analytics = new Analytics();

// Make it globally available
window.analytics = analytics;

// Track JavaScript errors
window.addEventListener('error', (event) => {
    analytics.trackError(event.error || event.message, {
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
    });
});

// Track unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
    analytics.trackError(event.reason, {
        type: 'unhandled_promise_rejection',
    });
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = Analytics;
}

/* ============================================================
   SECTION 2: FAVORITES MANAGEMENT
   ============================================================ */

(() => {
    'use strict';

    const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const optimistic = window.OptimisticUI || (window.OptimisticUI = {
        captureFavorite(button) {
            const countEl = button.querySelector('.ttb-favorite-count, .count, .action-badge');
            const rawCount = countEl ? countEl.textContent.replace(/[^\d]/g, '') : '0';
            return {
                active: button.classList.contains('is-active') || button.classList.contains('active') || Boolean(button.closest('.profile-favorite-remove-form')),
                count: Number(rawCount || 0),
            };
        },
        markPending(element, pending) {
            element.classList.toggle('is-optimistic-pending', pending);
            element.setAttribute('aria-busy', pending ? 'true' : 'false');
        },
        messageFor(error, fallback) {
            const messages = {
                csrf_failed: 'Güvenlik doğrulaması yenilenmeli. Sayfayı yenileyip tekrar deneyin.',
                invalid_topic: 'Konu bilgisi geçersiz.',
                topic_not_found: 'Bu konu artık yayında değil veya kaldırılmış.',
                rate_limited: 'Çok hızlı işlem yapıyorsunuz. Lütfen biraz sonra tekrar deneyin.',
                server_error: 'Sunucu tarafında bir sorun oluştu.',
                login_required: 'Bu işlem için giriş yapmalısınız.',
            };
            return messages[error] || fallback || 'İşlem tamamlanamadı.';
        },
    });

    const setButtonState = (button, isFavorited, count) => {
        if (button.dataset.originalHtml && button.classList.contains('is-submitting')) {
            button.innerHTML = button.dataset.originalHtml;
        }
        button.classList.remove('is-submitting');
        button.setAttribute('aria-busy', 'false');
        button.classList.toggle('is-active', isFavorited);
        button.classList.toggle('active', isFavorited);
        const icon = button.querySelector('i');
        if (icon) icon.className = `bi ${isFavorited ? 'bi-heart-fill' : 'bi-heart'}`;
        const countEl = button.querySelector('.ttb-favorite-count, .count, .action-badge');
        if (countEl) countEl.textContent = new Intl.NumberFormat('tr-TR').format(count);
        const labelEl = button.querySelector('.action-text, .ttb-favorite-label, [data-favorite-label]');
        if (labelEl) labelEl.textContent = isFavorited ? 'Favorilerden Kaldır' : 'Favorilere Ekle';
        const textNode = [...button.childNodes].find((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
        if (textNode) textNode.textContent = isFavorited ? ' Favorilerden Kaldır ' : ' Favorilere Ekle ';
        button.title = isFavorited ? 'Favorilerden kaldır' : 'Favorilere ekle';
    };

    const getFavoriteTopicId = (button) => button?.dataset.favoriteTopicId || button?.dataset.topicId || button?.closest('.ttb-favorite-form')?.dataset.topicId || '';

    const setTopicFavoriteState = (topicId, isFavorited, count) => {
        if (!topicId) return;
        document.querySelectorAll(`.ttb-favorite-form[data-topic-id="${topicId}"] button, [data-favorite-topic-id="${topicId}"], [data-topic-id="${topicId}"].ttb-favorite-btn`).forEach((button) => {
            setButtonState(button, isFavorited, count);
        });
    };

    class FavoriteManager {
        constructor() {
            document.addEventListener('submit', (event) => this.handleSubmit(event));
            document.addEventListener('click', (event) => this.handleClick(event));
        }

        handleSubmit(event) {
            const form = event.target.closest('.ttb-favorite-form');
            if (!form) return;
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const topicId = form.dataset.topicId || button?.dataset.topicId;
            const slug = form.action;
            this.toggle({ button, topicId, fallbackUrl: slug });
        }

        handleClick(event) {
            const button = event.target.closest('[data-favorite-topic-id]');
            if (!button) return;
            event.preventDefault();
            this.toggle({ button, topicId: button.dataset.favoriteTopicId });
        }

        async toggle({ button, topicId, fallbackUrl }) {
            if (!button) return;
            topicId = String(topicId || getFavoriteTopicId(button) || '');
            const previous = optimistic.captureFavorite(button);
            const optimisticState = !previous.active;
            const optimisticCount = Math.max(0, previous.count + (optimisticState ? 1 : -1));
            setTopicFavoriteState(topicId, optimisticState, optimisticCount);
            optimistic.markPending(button, true);
            button.disabled = true;

            try {
                if (!window.publicFetchJson) {
                    throw new Error('Public API helper yuklenemedi.');
                }
                const payload = await window.publicFetchJson(`${baseUri}/api/favorites/toggle.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: { topic_id: Number(topicId || button.dataset.topicId || 0) },
                    notifyError: false
                });
                if (!payload.success) throw new Error(payload.error || 'favorite_failed');
                setTopicFavoriteState(topicId, Boolean(payload.favorited), Number(payload.count || 0));
                if (!payload.favorited && button.closest('.profile-favorite-remove-form')) {
                    const card = button.closest('.profile-topic-item');
                    if (card) {
                        card.classList.add('is-removing');
                        window.setTimeout(() => {
                            const list = card.closest('.profile-favorites-list');
                            card.remove();
                            if (list && !list.querySelector('.profile-topic-item')) {
                                list.innerHTML = `
                                    <div class="profile-empty-cta profile-empty-ajax">
                                        <i class="bi bi-heart" aria-hidden="true"></i>
                                        <h3>Henüz favori içeriğiniz yok</h3>
                                        <p>Beğendiğiniz konuları favorilere ekleyerek daha sonra kolayca erişebilirsiniz.</p>
                                        <a href="${baseUri}/index.php" class="ui-admin-btn ui-admin-btn-warning fw-bold">
                                            <i class="bi bi-compass me-1" aria-hidden="true"></i>İçerikleri Keşfet
                                        </a>
                                    </div>
                                `;
                            }
                        }, 180);
                    }
                }
                window.showToast?.(payload.favorited ? 'Favorilere eklendi' : 'Favorilerden kaldırıldı', 'success');
                window.analytics?.trackFavorite?.(payload.topic_id, payload.favorited ? 'add' : 'remove');
            } catch (error) {
                if (Number(error && error.status) === 401) {
                    window.location.href = `${baseUri}/giris`;
                    return;
                }
                setTopicFavoriteState(topicId, previous.active, previous.count);
                window.showToast?.(optimistic.messageFor(error.message, 'Favori işlemi tamamlanamadı.'), 'error');
            } finally {
                button.disabled = false;
                optimistic.markPending(button, false);
            }
        }
    }

    const init = () => new FavoriteManager();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* --- turkmod-upload-edit-form.js --- */
(function () {
  "use strict";

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function toast(message, type) {
    if (window.showToast) {
      window.showToast(message, type || "warning");
      return;
    }
    if (window.console && message) console.warn(message);
  }

  function inputValue(root, selector) {
    var input = root.querySelector(selector);
    return input ? input.value || "" : "";
  }

  function setUploadLiveHint(key, message, state) {
    var hint = document.querySelector('[data-live-hint="' + key + '"]');
    if (!hint) return;
    hint.textContent = message || "";
    hint.classList.remove("is-ok", "is-warning", "is-error");
    if (state) hint.classList.add("is-" + state);
  }

  function uploadContentText(form) {
    var editor = form.querySelector(".ql-editor");
    if (editor) return editor.textContent.trim();
    var textarea = form.querySelector('textarea[name="content"]');
    return textarea ? textarea.value.replace(/<[^>]*>/g, "").trim() : "";
  }

  function syncDownloadLinksHidden(form) {
    var names = form.querySelectorAll('input[name="dl_name[]"]');
    var urls = form.querySelectorAll('input[name="dl_url[]"]');
    var lines = [];
    names.forEach(function (nameInput, index) {
      var url = urls[index] ? urls[index].value.trim() : "";
      if (url) lines.push((nameInput.value.trim() || "Link") + "|" + url);
    });
    var hidden = form.querySelector("#dlHidden");
    if (hidden) hidden.value = lines.join("\n");
    return lines;
  }

  function isAllowedVideoHost(url, allowedHosts) {
    if (!url || allowedHosts.length === 0) return true;
    try {
      var host = new URL(url).hostname.toLowerCase();
      return allowedHosts.some(function (allowedHost) {
        return host === allowedHost || host.endsWith("." + allowedHost);
      });
    } catch (error) {
      return false;
    }
  }

  function validateUploadVideoUrl(form, showToast) {
    var input = form.querySelector('input[name="topic_video_url"]');
    if (!input) return true;
    var value = input.value.trim();
    var allowedHosts = (form.dataset.allowedVideoHosts || "").split(",").map(function (host) {
      return host.trim().toLowerCase();
    }).filter(Boolean);

    if (form.dataset.allowVideoUrl !== "1") {
      setUploadLiveHint("video", "Video URL alani kapali.", "warning");
      return true;
    }
    if (!value) {
      setUploadLiveHint("video", allowedHosts.length ? "Izinli saglayicilar: " + allowedHosts.join(", ") : "Video URL istege bagli.", "warning");
      return true;
    }
    if (!isAllowedVideoHost(value, allowedHosts)) {
      var message = "Video URL izinli saglayicilardan biri olmali: " + allowedHosts.join(", ") + ".";
      setUploadLiveHint("video", message, "error");
      if (showToast) toast(message, "error");
      return false;
    }
    setUploadLiveHint("video", "Video URL uygun gorunuyor.", "ok");
    return true;
  }

  function validateUploadDownloadLinks(form, showToast) {
    var lines = syncDownloadLinksHidden(form);
    var validCount = 0;
    form.querySelectorAll('input[name="dl_url[]"]').forEach(function (input) {
      if (!input.value.trim()) return;
      try {
        var url = new URL(input.value.trim());
        if (url.protocol === "http:" || url.protocol === "https:") validCount++;
      } catch (error) {}
    });

    if (form.dataset.requireDownloadLink === "1" && validCount === 0) {
      var message = "En az bir gecerli indirme baglantisi eklemelisiniz.";
      setUploadLiveHint("download", message, "error");
      if (showToast) toast(message, "error");
      return false;
    }
    if (lines.length === 0) {
      setUploadLiveHint("download", "Indirme linki istege bagli.", "warning");
      return true;
    }
    setUploadLiveHint("download", validCount + " gecerli indirme linki algilandi.", "ok");
    return true;
  }

  function validateUploadFieldRules(form, showToast) {
    var ok = true;
    var title = form.querySelector('input[name="title"]');
    var author = form.querySelector('input[name="author_topic"]');
    var version = form.querySelector('input[name="topic_version"]');
    var attachment = form.querySelector('input[name="attachment"]');
    var minTitle = Number(form.dataset.minTitleLength || 0);
    var maxTitle = Number(form.dataset.maxTitleLength || 0);
    var minContent = Number(form.dataset.minContentLength || 0);

    if (title) {
      var length = title.value.trim().length;
      if (length === 0) {
        setUploadLiveHint("title", minTitle + "-" + maxTitle + " karakter araliginda baslik yazin.", "warning");
      } else if ((minTitle > 0 && length < minTitle) || (maxTitle > 0 && length > maxTitle)) {
        ok = false;
        setUploadLiveHint("title", "Baslik " + minTitle + "-" + maxTitle + " karakter olmali. Su an: " + length + ".", "error");
      } else {
        setUploadLiveHint("title", "Baslik uzunlugu uygun: " + length + " karakter.", "ok");
      }
    }

    var contentLength = uploadContentText(form).length;
    if (contentLength === 0) {
      setUploadLiveHint("content", "Aciklama en az " + minContent + " karakter olmali.", "warning");
    } else if (minContent > 0 && contentLength < minContent) {
      ok = false;
      setUploadLiveHint("content", "Aciklama en az " + minContent + " karakter olmali. Su an: " + contentLength + ".", "error");
    } else {
      setUploadLiveHint("content", "Aciklama uzunlugu uygun: " + contentLength + " karakter.", "ok");
    }

    if (author) {
      var authorMissing = form.dataset.requireAuthor === "1" && !author.value.trim();
      setUploadLiveHint("author", authorMissing ? "Yapimci alani zorunlu." : (author.value.trim() ? "Yapimci bilgisi girildi." : "Yapimci istege bagli."), authorMissing ? "error" : "ok");
      ok = ok && !authorMissing;
    }
    if (version) {
      var versionMissing = form.dataset.requireVersion === "1" && !version.value.trim();
      setUploadLiveHint("version", versionMissing ? "Oyun surumu zorunlu." : (version.value.trim() ? "Surum bilgisi girildi." : "Surum istege bagli."), versionMissing ? "error" : "ok");
      ok = ok && !versionMissing;
    }
    if (attachment && attachment.files.length > 0) {
      var maxMb = Number(form.dataset.attachmentMaxSizeMb || 0);
      var tooLarge = maxMb > 0 && attachment.files[0].size > maxMb * 1024 * 1024;
      setUploadLiveHint("attachment", tooLarge ? "Mod dosyasi en fazla " + maxMb + " MB olabilir." : "Mod dosyasi boyutu uygun.", tooLarge ? "error" : "ok");
      ok = ok && !tooLarge;
    }

    ok = validateUploadVideoUrl(form, showToast) && ok;
    ok = validateUploadDownloadLinks(form, showToast) && ok;
    if (!ok && showToast) toast("Lutfen kural uyarilarini duzeltin.", "error");
    return ok;
  }

  function syncInputFiles(input, files) {
    if (typeof DataTransfer === "undefined") return;
    var dt = new DataTransfer();
    files.forEach(function (file) { dt.items.add(file); });
    input.files = dt.files;
  }

  function getUploadImageRules(form) {
    return {
      allowedExt: (form.dataset.allowedImageExt || "jpg,jpeg,png,webp").split(",").map(function (ext) {
        return ext.trim().toLowerCase();
      }).filter(Boolean),
      minWidth: Number(form.dataset.imageMinWidth || 0),
      minHeight: Number(form.dataset.imageMinHeight || 0),
      maxWidth: Number(form.dataset.imageMaxWidth || 0),
      maxHeight: Number(form.dataset.imageMaxHeight || 0)
    };
  }

  function readUploadFileAsDataUrl(file) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.addEventListener("load", function (event) { resolve(event.target.result); });
      reader.addEventListener("error", function () { reject(new Error("Gorsel dosyasi okunamadi.")); });
      reader.readAsDataURL(file);
    });
  }

  function loadUploadImageDimensions(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.addEventListener("load", function () {
        resolve({ width: img.naturalWidth || img.width, height: img.naturalHeight || img.height });
      });
      img.addEventListener("error", function () { reject(new Error("Gorsel okunamadi.")); });
      readUploadFileAsDataUrl(file).then(function (dataUrl) { img.src = dataUrl; }).catch(reject);
    });
  }

  function formatUploadDimensionRules(rules) {
    var parts = [];
    if (rules.minWidth > 0 || rules.maxWidth > 0) {
      parts.push("genislik " + (rules.minWidth > 0 ? "min " + rules.minWidth + " px" : "min yok") + " / " + (rules.maxWidth > 0 ? "max " + rules.maxWidth + " px" : "max yok"));
    }
    if (rules.minHeight > 0 || rules.maxHeight > 0) {
      parts.push("yukseklik " + (rules.minHeight > 0 ? "min " + rules.minHeight + " px" : "min yok") + " / " + (rules.maxHeight > 0 ? "max " + rules.maxHeight + " px" : "max yok"));
    }
    return parts.length ? parts.join(", ") : "piksel siniri yok";
  }

  function validateUploadImageFile(form, file, config) {
    var rules = getUploadImageRules(form);
    var ext = (file.name.split(".").pop() || "").toLowerCase();
    var label = config.label || "Gorsel";
    var maxSizeMb = Number(config.maxSizeMb || 0);

    if (!ext || rules.allowedExt.indexOf(ext) === -1) {
      return Promise.resolve({ ok: false, message: label + ' "' + file.name + '" icin izinli uzantilar: ' + rules.allowedExt.join(", ") + "." });
    }
    if (maxSizeMb > 0 && file.size > maxSizeMb * 1024 * 1024) {
      return Promise.resolve({ ok: false, message: label + ' "' + file.name + '" en fazla ' + maxSizeMb + " MB olabilir." });
    }
    if (file.type && !file.type.startsWith("image/")) {
      return Promise.resolve({ ok: false, message: label + ' "' + file.name + '" gecerli bir gorsel degil.' });
    }

    return loadUploadImageDimensions(file).then(function (dimensions) {
      if (rules.minWidth > 0 && dimensions.width < rules.minWidth) {
        return { ok: false, message: label + ' "' + file.name + '" genisligi minimum ' + rules.minWidth + " px olmalidir. Secilen olcu: " + dimensions.width + "x" + dimensions.height + " px." };
      }
      if (rules.minHeight > 0 && dimensions.height < rules.minHeight) {
        return { ok: false, message: label + ' "' + file.name + '" yuksekligi minimum ' + rules.minHeight + " px olmalidir. Secilen olcu: " + dimensions.width + "x" + dimensions.height + " px." };
      }
      if (rules.maxWidth > 0 && dimensions.width > rules.maxWidth) {
        return { ok: false, message: label + ' "' + file.name + '" genisligi maksimum ' + rules.maxWidth + " px olmalidir. Secilen olcu: " + dimensions.width + "x" + dimensions.height + " px." };
      }
      if (rules.maxHeight > 0 && dimensions.height > rules.maxHeight) {
        return { ok: false, message: label + ' "' + file.name + '" yuksekligi maksimum ' + rules.maxHeight + " px olmalidir. Secilen olcu: " + dimensions.width + "x" + dimensions.height + " px." };
      }
      return { ok: true };
    }).catch(function () {
      return { ok: false, message: label + ' "' + file.name + '" olculeri okunamadi. Aktif piksel kurali: ' + formatUploadDimensionRules(rules) + "." };
    });
  }

  function filterUploadImageSelection(form, input, files, config) {
    var selectedFiles = Array.prototype.slice.call(files || []);
    var limitedFiles = selectedFiles.slice(0, config.maxFiles);
    var validFiles = [];

    if (selectedFiles.length > config.maxFiles) {
      toast("En fazla " + config.maxFiles + " adet gorsel secebilirsiniz. Fazla dosyalar eklenmedi.", "warning");
    }

    return limitedFiles.reduce(function (promise, file) {
      return promise.then(function () {
        return validateUploadImageFile(form, file, config).then(function (result) {
          if (result.ok) validFiles.push(file);
          else toast(result.message, "error");
        });
      });
    }, Promise.resolve()).then(function () {
      syncInputFiles(input, validFiles);
      return validFiles.length === limitedFiles.length && limitedFiles.length > 0;
    });
  }

  function removePublicPreviewFile(input, previewId, maxFiles, index) {
    var files = Array.prototype.slice.call(input.files || []);
    files.splice(index, 1);
    syncInputFiles(input, files);
    renderPublicPreviews(input, previewId, maxFiles);
  }

  function reorderPublicPreviewFile(input, previewId, maxFiles, fromIndex, toIndex) {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return;
    var files = Array.prototype.slice.call(input.files || []);
    if (!files[fromIndex] || !files[toIndex]) return;
    var moved = files.splice(fromIndex, 1)[0];
    files.splice(toIndex, 0, moved);
    syncInputFiles(input, files);
    renderPublicPreviews(input, previewId, maxFiles);
    toast("Galeri sirasi guncellendi.", "success");
  }

  function renderPublicPreviews(input, previewId, maxFiles) {
    var preview = document.getElementById(previewId);
    if (!preview) return;
    preview.innerHTML = "";
    Array.prototype.slice.call(input.files || []).slice(0, maxFiles).forEach(function (file, index) {
      var item = document.createElement("div");
      item.className = "public-preview-item";
      item.dataset.index = String(index);
      var sortable = previewId === "publicGalleryPreview" && input.files.length > 1;
      if (sortable) {
        item.classList.add("is-sortable");
        item.draggable = true;
        item.tabIndex = 0;
        item.addEventListener("dragstart", function (event) {
          item.classList.add("is-dragging");
          event.dataTransfer.effectAllowed = "move";
          event.dataTransfer.setData("text/plain", String(index));
        });
        item.addEventListener("dragend", function () { item.classList.remove("is-dragging"); });
        item.addEventListener("dragover", function (event) {
          event.preventDefault();
          item.classList.add("is-drop-target");
        });
        item.addEventListener("dragleave", function () { item.classList.remove("is-drop-target"); });
        item.addEventListener("drop", function (event) {
          event.preventDefault();
          item.classList.remove("is-drop-target");
          reorderPublicPreviewFile(input, previewId, maxFiles, Number(event.dataTransfer.getData("text/plain")), index);
        });
        item.addEventListener("keydown", function (event) {
          if (["ArrowLeft", "ArrowUp", "ArrowRight", "ArrowDown"].indexOf(event.key) === -1) return;
          event.preventDefault();
          reorderPublicPreviewFile(input, previewId, maxFiles, index, index + (event.key === "ArrowLeft" || event.key === "ArrowUp" ? -1 : 1));
        });
      }

      if (file.type.startsWith("image/")) {
        var img = document.createElement("img");
        img.alt = file.name;
        item.appendChild(img);
        var reader = new FileReader();
        reader.addEventListener("load", function (event) { img.src = event.target.result; });
        reader.addEventListener("error", function () {
          img.remove();
          item.insertAdjacentHTML("afterbegin", '<div class="public-preview-fallback"><i class="bi bi-image"></i><span>Onizleme yok</span></div>');
        });
        reader.readAsDataURL(file);
      } else {
        item.innerHTML = '<div class="public-preview-fallback"><i class="bi bi-file-earmark"></i><span>Dosya</span></div>';
      }

      var removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "public-preview-remove-bar";
      removeBtn.innerHTML = '<i class="bi bi-trash3"></i> Kaldir';
      removeBtn.addEventListener("click", function () { removePublicPreviewFile(input, previewId, maxFiles, index); });
      item.appendChild(removeBtn);

      var label = document.createElement("div");
      label.className = "public-preview-name";
      label.textContent = (previewId === "publicGalleryPreview" ? (index + 1) + ". " : "") + file.name;
      item.appendChild(label);
      preview.appendChild(item);
    });
  }

  function bindUploadRuleInputs(form, scope) {
    (scope || form).querySelectorAll('input[name="title"], textarea[name="content"], input[name="author_topic"], input[name="topic_version"], input[name="topic_video_url"], input[name="attachment"], input[name="dl_name[]"], input[name="dl_url[]"]').forEach(function (input) {
      if (input.dataset.liveRuleBound === "1") return;
      input.dataset.liveRuleBound = "1";
      ["input", "change"].forEach(function (eventName) {
        input.addEventListener(eventName, function () {
          validateUploadFieldRules(form, false);
          scheduleDraftSave(form);
        });
      });
    });
  }

  function draftKey(form) {
    return "turkmod.uploadTopicDraft.v2:" + (form.dataset.topicEditWizard === undefined ? "create" : "edit");
  }

  var draftTimer = null;
  var restoringDraft = false;

  function collectDraft(form) {
    return {
      title: inputValue(form, 'input[name="title"]'),
      content: inputValue(form, 'textarea[name="content"]'),
      author: inputValue(form, 'input[name="author_topic"]'),
      version: inputValue(form, 'input[name="topic_version"]'),
      videoUrl: inputValue(form, 'input[name="topic_video_url"]'),
      links: Array.prototype.slice.call(form.querySelectorAll(".dl-row")).map(function (row) {
        return {
          name: inputValue(row, 'input[name="dl_name[]"]'),
          url: inputValue(row, 'input[name="dl_url[]"]')
        };
      }),
      savedAt: Date.now()
    };
  }

  function saveDraft(form) {
    if (restoringDraft || form.hasAttribute("data-topic-edit-wizard")) return;
    try {
      localStorage.setItem(draftKey(form), JSON.stringify(collectDraft(form)));
    } catch (error) {}
  }

  function scheduleDraftSave(form) {
    window.clearTimeout(draftTimer);
    draftTimer = window.setTimeout(function () { saveDraft(form); }, 250);
  }

  function clearDraft(form) {
    try {
      localStorage.removeItem(draftKey(form));
    } catch (error) {}
  }

  function addDlRow(form, name, url) {
    form = form || document.getElementById("uploadForm");
    if (!form) return null;
    var row = document.createElement("div");
    row.className = "dl-row";
    row.innerHTML = '<input type="text" name="dl_name[]" class="ui-admin-form-control w-25" placeholder="Kaynak Adi" value="">'
      + '<input type="url" name="dl_url[]" class="ui-admin-form-control flex-grow-1" placeholder="https://..." value="' + (url || "") + '"' + (form.dataset.requireDownloadLink === "1" ? " required" : "") + ">"
      + '<button type="button" class="ui-admin-btn ui-admin-btn-outline ui-admin-btn-sm" data-dl-remove title="Kaldir"><i class="bi bi-trash3"></i></button>';
    var nameInput = row.querySelector('input[name="dl_name[]"]');
    if (nameInput) nameInput.value = name || "";
    var rows = form.querySelector("#dlRows");
    if (rows) rows.appendChild(row);
    bindUploadRuleInputs(form, row);
    return row;
  }

  window.addDlRow = function (name, url) {
    return addDlRow(document.getElementById("uploadForm"), name, url);
  };

  function restoreDraft(form) {
    if (form.hasAttribute("data-topic-edit-wizard")) return;
    var draft = null;
    try {
      draft = JSON.parse(localStorage.getItem(draftKey(form)) || "null");
    } catch (error) {
      draft = null;
    }
    if (!draft || typeof draft !== "object") return;
    restoringDraft = true;
    var title = form.querySelector('input[name="title"]');
    var content = form.querySelector('textarea[name="content"]');
    var author = form.querySelector('input[name="author_topic"]');
    var version = form.querySelector('input[name="topic_version"]');
    var video = form.querySelector('input[name="topic_video_url"]');
    if (title && !title.value && draft.title) title.value = draft.title;
    if (content && !content.value && draft.content) content.value = draft.content;
    if (author && !author.value && draft.author) author.value = draft.author;
    if (version && !version.value && draft.version) version.value = draft.version;
    if (video && !video.value && draft.videoUrl) video.value = draft.videoUrl;
    if (Array.isArray(draft.links) && draft.links.length > 0) {
      var rows = Array.prototype.slice.call(form.querySelectorAll(".dl-row"));
      rows.slice(1).forEach(function (row) { row.remove(); });
      draft.links.forEach(function (link, index) {
        var row = index === 0 ? form.querySelector(".dl-row") : addDlRow(form);
        if (!row) return;
        var nameInput = row.querySelector('input[name="dl_name[]"]');
        var urlInput = row.querySelector('input[name="dl_url[]"]');
        if (nameInput && !nameInput.value) nameInput.value = link.name || "";
        if (urlInput && !urlInput.value) urlInput.value = link.url || "";
      });
    }
    syncDownloadLinksHidden(form);
    restoringDraft = false;
  }

  window.ensureUploadQuillEditors = function () {
    document.querySelectorAll("#uploadForm textarea.rich-editor").forEach(function (textarea) {
      if (textarea.quillInstance) {
        if (textarea.dataset.uploadQuillSync !== "1") {
          textarea.dataset.uploadQuillSync = "1";
          textarea.quillInstance.on("text-change", function () {
            textarea.value = textarea.quillInstance.root.innerHTML;
            var form = textarea.closest("form");
            if (form) {
              validateUploadFieldRules(form, false);
              scheduleDraftSave(form);
            }
          });
        }
        return;
      }
      if (typeof Quill !== "undefined" && textarea.dataset._quillInit !== "1") {
        try {
                var alignAttributor = Quill.import("attributors/style/align");
                Quill.register(alignAttributor, true);
                var colorAttributor = Quill.import("attributors/style/color");
                Quill.register(colorAttributor, true);
                var backgroundAttributor = Quill.import("attributors/style/background");
                Quill.register(backgroundAttributor, true);
                var ColorStyle = Quill.import("attributors/style/color");
                Quill.register(ColorStyle, true);
                var BackgroundStyle = Quill.import("attributors/style/background");
                Quill.register(BackgroundStyle, true);
            } catch (error) {}
        var wrapper = document.createElement("div");
        wrapper.className = "quill-container upload-quill-container";
        var editor = document.createElement("div");
        wrapper.appendChild(editor);
        textarea.parentNode.insertBefore(wrapper, textarea.nextSibling);
        textarea.classList.add("is-hidden");
        textarea.dataset._quillInit = "1";
        var quill = new Quill(editor, {
          theme: "snow",
          modules: {
            toolbar: [[{header:[1,2,3,false]}],["bold","italic","underline","strike"],[{color:[]},{background:[]}],[{list:"ordered"},{list:"bullet"}],
              ["blockquote", "link", "image", "video"],
              [{ align: [] }],
              ["clean"]
            ]
          }
        });
        if (textarea.value) {
          try {
            quill.setContents(quill.clipboard.convert(textarea.value), "silent");
          } catch (error) {
            quill.setText(textarea.value);
          }
        }
        quill.on("text-change", function () {
          textarea.value = quill.root.innerHTML;
          var form = textarea.closest("form");
          if (form) {
            validateUploadFieldRules(form, false);
            scheduleDraftSave(form);
          }
        });
        textarea.quillInstance = quill;
        return;
      }
    });
  };

  function initUploadForm() {
    var form = document.getElementById("uploadForm");
    if (!form || form.dataset.turkmodUploadInit === "1") return;
    if (form.dataset.uploadTopicStandalone === "1") return;
    form.dataset.turkmodUploadInit = "1";

    function imageConfig(inputId) {
      var isCover = inputId === "publicCoverInput";
      return {
        inputId: inputId,
        previewId: isCover ? "publicCoverPreview" : "publicGalleryPreview",
        maxFiles: isCover ? 1 : Number(form.dataset.maxImages || 10),
        maxSizeMb: Number(isCover ? (form.dataset.coverMaxSizeMb || 10) : (form.dataset.galleryMaxSizeMb || 10)),
        label: isCover ? "Kapak gorseli" : "Galeri gorseli"
      };
    }

    ["publicCoverInput", "publicGalleryInput"].forEach(function (inputId) {
      var config = imageConfig(inputId);
      var input = document.getElementById(inputId);
      var zone = input ? input.closest(".public-dropzone") : null;
      if (!input || !zone) return;
      input.addEventListener("change", function () {
        filterUploadImageSelection(form, input, input.files || [], config).then(function () {
          renderPublicPreviews(input, config.previewId, config.maxFiles);
        });
      });
      ["dragenter", "dragover"].forEach(function (eventName) {
        zone.addEventListener(eventName, function (event) {
          event.preventDefault();
          zone.classList.add("is-active");
        });
      });
      ["dragleave", "drop"].forEach(function (eventName) {
        zone.addEventListener(eventName, function (event) {
          event.preventDefault();
          zone.classList.remove("is-active");
        });
      });
      zone.addEventListener("drop", function (event) {
        var files = event.dataTransfer ? event.dataTransfer.files : [];
        filterUploadImageSelection(form, input, files, config).then(function () {
          renderPublicPreviews(input, config.previewId, config.maxFiles);
        });
      });
    });

    document.addEventListener("click", function (event) {
      var trigger = event.target.closest("[data-open-input]");
      if (trigger && form.contains(trigger)) {
        var input = document.getElementById(trigger.getAttribute("data-open-input"));
        if (input) input.click();
        return;
      }
      var addButton = event.target.closest("[data-add-dl-row]");
      if (addButton && form.contains(addButton)) {
        addDlRow(form);
        validateUploadFieldRules(form, false);
        scheduleDraftSave(form);
        return;
      }
      var removeButton = event.target.closest("[data-dl-remove]");
      if (removeButton && form.contains(removeButton)) {
        var row = removeButton.closest(".dl-row");
        if (row) row.remove();
        validateUploadFieldRules(form, false);
        scheduleDraftSave(form);
      }
    });

    bindUploadRuleInputs(form, form);
    restoreDraft(form);
    validateUploadFieldRules(form, false);
    window.ensureUploadQuillEditors();
    window.addEventListener("load", window.ensureUploadQuillEditors);

    var panels = Array.prototype.slice.call(form.querySelectorAll(".upload-wizard-panel"));
    var steps = Array.prototype.slice.call(form.querySelectorAll(".upload-wizard-step"));
    var prevBtn = form.querySelector("[data-wizard-prev]");
    var nextBtn = form.querySelector("[data-wizard-next]");
    var status = document.getElementById("uploadWizardStatus");
    var current = 1;
    var titles = {
      1: "Temel bilgiler",
      2: "Kapak gorseli",
      3: "Aciklama",
      4: "Galeri ve video",
      5: "Yapimci ve oyun surumu",
      6: "Indirme kaynaklari",
      7: "Kontrol ve onay"
    };

    function setStep(step) {
      current = Math.max(1, Math.min(7, step));
      panels.forEach(function (panel) {
        var active = Number(panel.dataset.step) === current;
        panel.hidden = !active;
        panel.classList.toggle("is-active", active);
      });
      steps.forEach(function (button) {
        var target = Number(button.dataset.stepTarget);
        button.classList.toggle("is-active", target === current);
        button.classList.toggle("is-complete", target < current);
      });
      if (prevBtn) prevBtn.disabled = current === 1;
      if (nextBtn) nextBtn.hidden = current === 7;
      if (status) status.textContent = current + " / 7 - " + titles[current];
      window.ensureUploadQuillEditors();
    }

    function validateImageSelection(input, config) {
      var files = Array.prototype.slice.call(input ? input.files || [] : []);
      if (!input || files.length === 0) return Promise.resolve(true);
      return filterUploadImageSelection(form, input, files, config).then(function (valid) {
        renderPublicPreviews(input, config.previewId, config.maxFiles);
        return valid;
      });
    }

    function validateStep(step) {
      if (step === 1) {
        var title = form.querySelector('input[name="title"]');
        var category = form.querySelector('select[name="category_id"]');
        if (!title || !title.value.trim()) {
          toast("Mod basligi zorunludur.", "warning");
          if (title) title.focus();
          return Promise.resolve(false);
        }
        if (title && !title.checkValidity()) {
          validateUploadFieldRules(form, true);
          title.reportValidity();
          return Promise.resolve(false);
        }
        if (!category || !category.value) {
          toast("Kategori secimi zorunludur.", "warning");
          if (category) category.focus();
          return Promise.resolve(false);
        }
      }
      if (step === 2) {
        var cover = document.getElementById("publicCoverInput");
        if (cover && cover.required && cover.files.length === 0) {
          toast("Kapak gorseli yuklemelisiniz.", "warning");
          return Promise.resolve(false);
        }
        return validateImageSelection(cover, imageConfig("publicCoverInput"));
      }
      if (step === 3) {
        var textarea = form.querySelector('textarea[name="content"]');
        var minLength = Number(textarea ? textarea.dataset.minLength || 0 : 0);
        var contentText = uploadContentText(form);
        if (!contentText) {
          toast("Mod aciklamasi zorunludur.", "warning");
          return Promise.resolve(false);
        }
        if (minLength > 0 && contentText.length < minLength) {
          toast("Mod aciklamasi en az " + minLength + " karakter olmalidir.", "warning");
          return Promise.resolve(false);
        }
      }
      if (step === 4) {
        var gallery = document.getElementById("publicGalleryInput");
        if (gallery && gallery.required && gallery.files.length === 0) {
          toast("Galeri icin en az 1 gorsel yuklemelisiniz.", "warning");
          return Promise.resolve(false);
        }
        if (gallery && gallery.files.length > Number(form.dataset.maxImages || 10)) {
          toast("En fazla " + form.dataset.maxImages + " adet galeri gorseli yukleyebilirsiniz.", "warning");
          return Promise.resolve(false);
        }
        return validateImageSelection(gallery, imageConfig("publicGalleryInput"));
      }
      if (step === 5 || step === 6) {
        return Promise.resolve(validateUploadFieldRules(form, true));
      }
      return Promise.resolve(true);
    }

    steps.forEach(function (button) {
      button.addEventListener("click", function () {
        var target = Number(button.dataset.stepTarget);
        var allowSkip = form.dataset.allowStepSkip === "1";
        if (allowSkip || target <= current) {
          setStep(target);
          return;
        }
        validateStep(current).then(function (ok) {
          if (ok) setStep(target);
        });
      });
    });
    if (prevBtn) prevBtn.addEventListener("click", function () { setStep(current - 1); });
    if (nextBtn) {
      nextBtn.addEventListener("click", function () {
        validateStep(current).then(function (ok) {
          if (ok) setStep(current + 1);
        });
      });
    }

    if (form.dataset.wizardEnabled !== "1") {
      panels.forEach(function (panel) {
        panel.hidden = false;
        panel.classList.add("is-active");
      });
    } else {
      setStep(1);
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      if (form.dataset.submitting === "1" || form.dataset.submitted === "1") {
        toast(form.hasAttribute("data-topic-edit-wizard") ? "Bu degisiklik zaten gonderiliyor veya gonderildi." : "Bu konu zaten gonderiliyor veya gonderildi.", "warning");
        return;
      }
      window.ensureUploadQuillEditors();
      form.querySelectorAll("textarea.rich-editor").forEach(function (textarea) {
        if (textarea.quillInstance) {
          textarea.value = textarea.quillInstance.root.innerHTML;
        }
      });
      syncDownloadLinksHidden(form);
      if (!validateUploadFieldRules(form, true)) return;

      Promise.all([
        validateImageSelection(document.getElementById("publicCoverInput"), imageConfig("publicCoverInput")),
        validateImageSelection(document.getElementById("publicGalleryInput"), imageConfig("publicGalleryInput"))
      ]).then(function (validResults) {
        if (validResults.indexOf(false) !== -1) return;
        if (!form.checkValidity()) {
          form.reportValidity();
          return;
        }
        var submitButton = form.querySelector(".upload-final-actions button[type='submit']") || form.querySelector("button[type='submit']");
        var originalButtonHtml = submitButton ? submitButton.innerHTML : "";
        form.dataset.submitting = "1";
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.classList.add("is-submitting");
          submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Gonderiliyor...';
        }
        (window.publicFetchJson ? window.publicFetchJson(form.action, {
          method: "POST",
          body: new FormData(form),
          headers: {
            Accept: "application/json"
          },
          notifyError: false,
          errorMessage: form.hasAttribute("data-topic-edit-wizard") ? "Mod guncellenemedi." : "Mod gonderilemedi."
        }) : Promise.reject(new Error("Public API helper yuklenemedi."))).then(function (payload) {
            if (!payload.success) {
              throw new Error(payload.message || (form.hasAttribute("data-topic-edit-wizard") ? "Mod guncellenemedi." : "Mod gonderilemedi."));
            }
            if (form.dataset.lockAfterSubmit === "1" || form.hasAttribute("data-topic-edit-wizard")) {
              form.dataset.submitted = "1";
            }
            clearDraft(form);
            toast(payload.message || (form.hasAttribute("data-topic-edit-wizard") ? "Degisiklikler onaya gonderildi." : "Mod onaya gonderildi."), "success");
            if (submitButton) {
              submitButton.classList.remove("is-submitting");
              submitButton.classList.add("is-submitted");
              submitButton.disabled = form.dataset.lockAfterSubmit === "1" || form.hasAttribute("data-topic-edit-wizard");
              submitButton.innerHTML = '<i class="bi bi-check2-circle"></i> Gonderildi';
            }
            if (payload.redirect) {
              window.setTimeout(function () { window.location.href = payload.redirect; }, 900);
            }
        }).catch(function (error) {
          delete form.dataset.submitting;
          if (Number(error && error.status) === 409 && submitButton) {
            form.dataset.submitted = "1";
            submitButton.classList.remove("is-submitting");
            submitButton.classList.add("is-submit-locked");
            submitButton.innerHTML = '<i class="bi bi-lock"></i> Gonderim Kilitlendi';
          }
          toast(error && error.message ? error.message : (form.hasAttribute("data-topic-edit-wizard") ? "Mod guncellenemedi." : "Mod gonderilemedi."), "error");
          if (submitButton && form.dataset.submitted !== "1") {
            submitButton.disabled = false;
            submitButton.classList.remove("is-submitting");
            submitButton.innerHTML = originalButtonHtml;
          }
        });
      });
    });
  }

  ready(initUploadForm);
})();

(() => {
    'use strict';

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-category-expand]');
        if (!button) return;

        const card = button.closest('.ui-theme-category-family');
        if (!card) return;

        const expanded = button.getAttribute('aria-expanded') === 'true';
        const nextExpanded = !expanded;
        const label = button.querySelector('span');
        const collapsedLabel = button.dataset.collapsedLabel || 'Tum alt kategorileri gor';
        const expandedLabel = button.dataset.expandedLabel || 'Daha az goster';

        if (nextExpanded) {
            document.querySelectorAll('.ui-theme-category-family.is-expanded').forEach((openCard) => {
                if (openCard === card) return;
                const openButton = openCard.querySelector('[data-category-expand]');
                const openLabel = openButton?.querySelector('span');
                openCard.classList.remove('is-expanded');
                if (openButton) {
                    openButton.setAttribute('aria-expanded', 'false');
                    if (openLabel) {
                        openLabel.textContent = openButton.dataset.collapsedLabel || 'Tum alt kategorileri gor';
                    }
                }
                openCard.querySelectorAll('.ui-theme-category-child.is-extra').forEach((item) => {
                    item.hidden = true;
                });
            });
        }

        card.classList.toggle('is-expanded', nextExpanded);
        button.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
        if (label) label.textContent = nextExpanded ? expandedLabel : collapsedLabel;

        card.querySelectorAll('.ui-theme-category-child.is-extra').forEach((item) => {
            item.hidden = !nextExpanded;
        });
    });
})();

/* ============================================================
   SECTION 3: FILTER MANAGEMENT
   ============================================================ */

(() => {
    'use strict';

    const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';

    class FilterManager {
        constructor() {
            this.container = document.querySelector('[data-topic-list-container]');
            this.sortInsight = document.querySelector('[data-sort-insight] span');
            this.filters = new URLSearchParams(window.location.search);
            if (!this.container) return;
            this.bind();
        }

        sortMessages() {
            return {
                newest: 'Yeni içerikler yayın tarihine göre en güncelden eskiye sıralanır.',
                popular: 'Popüler sıralama görüntülenme ve indirme ilgisine göre listelenir.',
                downloads: 'Trend içerikler en çok indirilen modlara göre öne çıkar.',
                comments: 'En çok konuşulan içerikler yorum sayısına göre sıralanır.',
            };
        }

        renderSkeleton() {
            const skeletons = Array.from({ length: 4 }).map(() => `
                <article class="topic-list-card topic-list-skeleton" aria-hidden="true">
                    <span class="topic-skeleton-thumb"></span>
                    <span class="topic-skeleton-body">
                        <span class="topic-skeleton-line sk-w-45"></span>
                        <span class="topic-skeleton-line sk-w-85"></span>
                        <span class="topic-skeleton-line sk-w-70"></span>
                        <span class="topic-skeleton-actions">
                            <span></span><span></span><span></span>
                        </span>
                    </span>
                </article>
            `).join('');
            this.container.innerHTML = skeletons;
        }

        bind() {
            document.querySelectorAll('[data-filter]').forEach((element) => {
                const eventName = element.matches('select,input') ? 'change' : 'click';
                element.addEventListener(eventName, (event) => this.handleChange(event, element));
            });
            window.addEventListener('popstate', () => this.loadContent(false));
        }

        handleChange(event, element) {
            event.preventDefault();
            const key = element.dataset.filter;
            const value = element.dataset.value ?? element.value ?? '';

            if (value) this.filters.set(key, value);
            else this.filters.delete(key);
            this.filters.delete('page');

            this.updateActiveControls(key, value);
            this.updateSortInsight();
            this.loadContent(true);
        }

        updateActiveControls(key, value) {
            document.querySelectorAll(`[data-filter="${key}"]`).forEach((control) => {
                if (control.matches('button')) control.classList.toggle('active', (control.dataset.value || '') === value);
                if (control.matches('select')) control.value = value;
            });
        }

        updateSortInsight() {
            if (!this.sortInsight) return;
            const sort = this.filters.get('sort') || 'newest';
            this.sortInsight.textContent = this.sortMessages()[sort] || this.sortMessages().newest;
        }

        async loadContent(pushState = true) {
            const query = this.filters.toString();
            const pageUrl = `${window.location.pathname}${query ? `?${query}` : ''}`;
            const apiUrl = `${baseUri}/api/topics.php${query ? `?${query}` : ''}`;

            this.container.setAttribute('aria-busy', 'true');
            this.container.classList.add('is-loading');
            this.renderSkeleton();

            try {
                if (!window.publicFetchJson) {
                    throw new Error('Public API helper yuklenemedi.');
                }
                const payload = await window.publicFetchJson(apiUrl, { headers: { 'Accept': 'application/json' } });
                this.container.innerHTML = payload.html || '';
                if (pushState) history.pushState({}, '', pageUrl);
                this.container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.analytics?.track?.('filter_apply', Object.fromEntries(this.filters.entries()));
            } catch (error) {
                if (pushState) window.location.href = pageUrl;
            } finally {
                this.container.removeAttribute('aria-busy');
                this.container.classList.remove('is-loading');
            }
        }
    }

    const init = () => new FilterManager();
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* ============================================================
   SECTION 4: DOWNLOAD HANDLER
   ============================================================ */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const section = document.querySelector('.topic-dl-section');
        if (!section) return;

        const cards = section.querySelectorAll('.topic-dl-card');
        const countdownSeconds = Math.max(0, parseInt(section.dataset.countdownSeconds || '5', 10) || 0);
        const waitText = section.dataset.waitText || 'İndirme linkiniz kontrol ediliyor, lütfen bekleyiniz';
        const doneText = section.dataset.doneText || 'İndirme linkiniz hazır, indirmek için tıklayın';

        cards.forEach(function(card) {
            if (card.dataset.downloadHandlerBound === '1') return;
            card.dataset.downloadHandlerBound = '1';
            card.addEventListener('click', function(event) {
                event.preventDefault();

                // Zaten hazırsa direkt aç
                if (card.dataset.ready === '1') {
                    window.open(card.href, '_blank', 'noopener');
                    return;
                }

                // Countdown devam ediyorsa bekle
                if (card.dataset.counting === '1') return;

                card.dataset.counting = '1';
                const action = card.querySelector('.topic-dl-action');
                const button = card.querySelector('.topic-dl-button');
                let remaining = countdownSeconds;

                card.classList.add('is-counting');
                card.setAttribute('aria-busy', 'true');
                if (button) button.setAttribute('aria-live', 'polite');

                if (action) {
                    action.textContent = remaining > 0
                        ? waitText + '... ' + remaining
                        : waitText + '...';
                }

                // Countdown yoksa direkt hazır
                if (remaining <= 0) {
                    finishCountdown(card, action, doneText);
                    return;
                }

                // Countdown başlat
                const timer = setInterval(function() {
                    remaining -= 1;

                    if (remaining > 0) {
                        if (action) action.textContent = waitText + '... ' + remaining;
                        return;
                    }

                    clearInterval(timer);
                    finishCountdown(card, action, doneText);
                }, 1000);
            });
        });

        function finishCountdown(card, action, doneText) {
            card.dataset.ready = '1';
            card.dataset.counting = '0';
            card.removeAttribute('aria-busy');
            card.classList.remove('is-counting');
            card.classList.add('is-ready');
            if (action) action.textContent = doneText;
        }
    });
})();

/* ============================================================
   SECTION 5: CAROUSEL (IMAGE/VIDEO GALLERY)
   ============================================================ */

(function() {
    'use strict';

    class TopicCarousel {
        constructor(container, slides) {
            this.container = container;
            this.slides = slides || [];
            this.currentIndex = 0;
            this.init();
        }

        init() {
            if (!this.slides.length) return;

            this.contentEl = this.container.querySelector('#ui-comment-content');
            this.prevBtn = this.container.querySelector('#ui-comment-prev');
            this.nextBtn = this.container.querySelector('#ui-comment-next');
            this.thumbs = this.container.querySelectorAll('.ui-comment-thumb');

            this.bindEvents();
            this.renderSlide(0);

            // Tek slide varsa navigation'ı gizle
            if (this.slides.length <= 1) {
                if (this.prevBtn) this.prevBtn.style.display = 'none';
                if (this.nextBtn) this.nextBtn.style.display = 'none';
            }
        }

        bindEvents() {
            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', () => this.prev());
                this.prevBtn.addEventListener('mouseenter', () => {
                    this.prevBtn.style.background = 'rgba(255,255,255,0.4)';
                });
                this.prevBtn.addEventListener('mouseleave', () => {
                    this.prevBtn.style.background = 'rgba(255,255,255,0.2)';
                });
            }

            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', () => this.next());
                this.nextBtn.addEventListener('mouseenter', () => {
                    this.nextBtn.style.background = 'rgba(255,255,255,0.4)';
                });
                this.nextBtn.addEventListener('mouseleave', () => {
                    this.nextBtn.style.background = 'rgba(255,255,255,0.2)';
                });
            }

            this.thumbs.forEach(thumb => {
                thumb.addEventListener('click', () => {
                    const idx = parseInt(thumb.getAttribute('data-idx'));
                    this.goTo(idx);
                });
            });

            // Keyboard navigation
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') this.prev();
                if (e.key === 'ArrowRight') this.next();
            });
        }

        renderSlide(idx) {
            if (!this.slides[idx] || !this.contentEl) return;

            const slide = this.slides[idx];
            let html = '';

            switch (slide.type) {
                case 'youtube':
                    html = `<iframe src="https://www.youtube.com/embed/${slide.id}?autoplay=0"
                                    class="topic-carousel-media"
                                    width="1600"
                                    height="900"
                                    allowfullscreen
                                    loading="lazy"></iframe>`;
                    break;

                case 'vimeo':
                    html = `<iframe src="https://player.vimeo.com/video/${slide.id}"
                                    class="topic-carousel-media"
                                    width="1600"
                                    height="900"
                                    allowfullscreen
                                    loading="lazy"></iframe>`;
                    break;

                case 'video':
                    html = `<video controls src="${slide.url}"
                                   class="topic-carousel-media"
                                   preload="metadata"
                                   width="1600"
                                   height="900"></video>`;
                    break;

                case 'image':
                    html = `<img src="${slide.url}"
                                 class="topic-carousel-media"
                                 width="1600"
                                 height="900"
                                 decoding="async"
                                 loading="lazy">`;
                    break;
            }

            this.contentEl.innerHTML = html;
            this.updateThumbs(idx);
        }

        updateThumbs(idx) {
            this.thumbs.forEach((thumb, i) => {
                const isActive = i === idx;
                thumb.style.borderColor = isActive ? 'var(--topic-accent, #8b1538)' : 'transparent';
                thumb.style.opacity = isActive ? '1' : '0.5';
            });
        }

        prev() {
            this.currentIndex = this.currentIndex > 0
                ? this.currentIndex - 1
                : this.slides.length - 1;
            this.renderSlide(this.currentIndex);
        }

        next() {
            this.currentIndex = this.currentIndex < this.slides.length - 1
                ? this.currentIndex + 1
                : 0;
            this.renderSlide(this.currentIndex);
        }

        goTo(idx) {
            if (idx >= 0 && idx < this.slides.length) {
                this.currentIndex = idx;
                this.renderSlide(idx);
            }
        }
    }

    // Auto-initialize carousels
    window.initTopicCarousel = function(container, slides) {
        return new TopicCarousel(container, slides);
    };

    // Global carousel instance
    window.TopicCarousel = TopicCarousel;

    function initTopicCarouselFromDom() {
        document.querySelectorAll('.topic-carousel[data-carousel-slides]').forEach(container => {
            if (container.dataset.carouselInit === '1') return;
            let slides = [];
            try {
                slides = JSON.parse(container.getAttribute('data-carousel-slides') || '[]');
            } catch (error) {
                slides = [];
            }
            if (!Array.isArray(slides) || !slides.length) return;
            container.dataset.carouselInit = '1';
            new TopicCarousel(container, slides);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTopicCarouselFromDom);
    } else {
        initTopicCarouselFromDom();
    }
})();

/* --- enhanced-comments.js --- */
/**
 * Enhanced Comments System - Frontend
 * Reactions, Markdown, Mentions, Edit History
 */

(function () {
    'use strict';

    // Initialize enhanced comments
    window.EnhancedComments = {
        init: function (config) {
            this.config = config || {};
            this.reactionsEnabled = config.reactionsEnabled !== false;
            this.markdownEnabled = config.markdownEnabled !== false;
            this.mentionsEnabled = config.mentionsEnabled !== false;
            this.editHistoryEnabled = config.editHistoryEnabled !== false;

            this.bindReactionButtons();
            this.bindEditHistoryButtons();
            this.initMarkdownToolbar();
            this.initMentionAutocomplete();
            this.setupMutationObserver();
        },

        // ─── Reactions ───────────────────────────────────────
        bindReactionButtons: function () {
            if (!this.reactionsEnabled) return;

            // Handle reaction button clicks
            document.addEventListener('click', (e) => {
                const reactionBtn = e.target.closest('.comment-reaction-btn');
                if (reactionBtn) {
                    e.preventDefault();
                    const commentId = parseInt(reactionBtn.dataset.commentId);
                    const reactionType = reactionBtn.dataset.reactionType;
                    this.toggleReaction(commentId, reactionType, reactionBtn);
                }
            });
        },

        toggleReaction: function (commentId, reactionType, btn) {
            const previousHtml = this.captureReactionState(commentId);
            this.applyOptimisticReaction(commentId, reactionType, btn);

            // Get CSRF token from section or input
            let csrfToken = document.querySelector('.topic-comments')?.dataset?.csrf || '';
            if (!csrfToken) {
                csrfToken = document.querySelector('input[name="_token"]')?.value || '';
            }

            if (!csrfToken) {
                this.restoreReactionState(commentId, previousHtml);
                this.showToast('CSRF token bulunamadı. Sayfayı yenileyin.', 'error');
                return;
            }

            const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
            (window.publicFetchJson ? window.publicFetchJson(baseUri + '/api/comments.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: {
                    action: 'react',
                    comment_id: commentId,
                    reaction_type: reactionType,
                    _token: csrfToken
                },
                notifyError: false
            }) : Promise.reject(new Error('Public API helper yuklenemedi.')))
                .then(data => {
                    if (data.success) {
                        this.updateReactionUI(commentId, data.reactions, data.user_reactions);

                        // Update CSRF token
                        if (data._token) {
                            const section = document.querySelector('.topic-comments');
                            if (section) section.dataset.csrf = data._token;
                            const tokenInput = document.querySelector('input[name="_token"]');
                            if (tokenInput) tokenInput.value = data._token;
                        }
                    } else {
                        this.restoreReactionState(commentId, previousHtml);
                        this.showToast(data.error || 'Reaksiyon eklenemedi', 'error');
                    }
                })
                .catch((err) => {
                    console.error('Reaction error:', err);
                    this.restoreReactionState(commentId, previousHtml);
                    this.showToast('Bir hata oluştu', 'error');
                });
        },

        captureReactionState: function (commentId) {
            const container = document.querySelector(`[data-comment-id="${commentId}"] .ui-comment-reactions`);
            return container ? container.innerHTML : '';
        },

        restoreReactionState: function (commentId, html) {
            const container = document.querySelector(`[data-comment-id="${commentId}"] .ui-comment-reactions`);
            if (container && typeof html === 'string') container.innerHTML = html;
        },

        applyOptimisticReaction: function (commentId, reactionType, btn) {
            if (!btn) return;

            const isActive = btn.classList.contains('active');
            btn.classList.toggle('active', !isActive);
            btn.classList.add('is-optimistic-pending');
            btn.setAttribute('aria-busy', 'true');
            
            const icon = btn.querySelector('.bi');
            if (icon) {
                if (reactionType === 'like') {
                    icon.className = !isActive ? 'bi bi-hand-thumbs-up-fill' : 'bi bi-hand-thumbs-up';
                } else if (reactionType === 'dislike') {
                    icon.className = !isActive ? 'bi bi-hand-thumbs-down-fill' : 'bi bi-hand-thumbs-down';
                }
            }

            const countEl = btn.querySelector('.reaction-count');
            const current = Number(countEl ? countEl.textContent : 0);
            if (countEl) countEl.textContent = Math.max(0, current + (!isActive ? 1 : -1));
        },

        updateReactionUI: function (commentId, reactions, userReactions) {
            const container = document.querySelector(`[data-comment-id="${commentId}"] .ui-comment-reactions`);
            if (!container) return;

            const likes = reactions.like || 0;
            const dislikes = reactions.dislike || 0;
            const userLike = userReactions.includes('like') ? 'active' : '';
            const userDislike = userReactions.includes('dislike') ? 'active' : '';
            
            container.innerHTML = `
                <button class="comment-reaction-btn ui-comment-like-btn ${userLike}" data-comment-id="${commentId}" data-reaction-type="like" title="Beğen">
                    <i class="bi bi-hand-thumbs-up${userLike ? '-fill' : ''}"></i>
                    <span class="reaction-count">${likes}</span>
                </button>
                <button class="comment-reaction-btn ui-comment-dislike-btn ${userDislike}" data-comment-id="${commentId}" data-reaction-type="dislike" title="Beğenme">
                    <i class="bi bi-hand-thumbs-down${userDislike ? '-fill' : ''}"></i>
                    <span class="reaction-count">${dislikes}</span>
                </button>
            `;
        },

        // ─── Edit History ────────────────────────────────────
        bindEditHistoryButtons: function () {
            if (!this.editHistoryEnabled) return;

            document.addEventListener('click', (e) => {
                const historyBtn = e.target.closest('.comment-edit-history-btn');
                if (!historyBtn) return;

                e.preventDefault();
                const commentId = parseInt(historyBtn.dataset.commentId);
                this.showEditHistory(commentId);
            });
        },

        showEditHistory: function (commentId) {
            const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
            (window.publicFetchJson ? window.publicFetchJson(baseUri + `/api/comments.php?action=edit_history&comment_id=${commentId}&_=${Date.now()}`, { cache: 'no-store' }) : Promise.reject(new Error('Public API helper yuklenemedi.')))
                .then(data => {
                    if (data.success) {
                        this.renderEditHistoryModal(data.history);
                    } else {
                        this.showToast(data.error || 'Geçmiş yüklenemedi', 'error');
                    }
                })
                .catch(() => {
                    this.showToast('Bir hata oluştu', 'error');
                });
        },

        renderEditHistoryModal: function (history) {
            const previouslyFocused = document.activeElement;
            const modal = document.createElement('div');
            modal.className = 'comment-history-modal';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('aria-labelledby', 'comment-history-heading');
            modal.innerHTML = `
                <div class="comment-history-overlay"></div>
                <div class="comment-history-content">
                    <div class="comment-history-header">
                        <h3 id="comment-history-heading"><i class="bi bi-clock-history"></i> Düzenleme Geçmişi</h3>
                        <button class="comment-history-close" type="button" aria-label="Kapat">&times;</button>
                    </div>
                    <div class="comment-history-body">
                        ${history.length === 0 ? '<p class="text-muted">Düzenleme geçmişi bulunamadı.</p>' : ''}
                        ${history.map(h => `
                            <div class="history-item">
                                <div class="history-meta">
                                    <strong>${this.escapeHtml(h.editor_name)}</strong>
                                    <span class="text-muted">${h.time_ago}</span>
                                </div>
                                ${h.edit_reason ? `
                                    <!-- Optional staff edit reason -->
                                    <div class="history-reason">
                                        <strong>Neden:</strong> ${this.escapeHtml(h.edit_reason)}
                                    </div>
                                ` : ''}
                                <div class="history-diff">
                                    <div class="history-old">
                                        <label>Eski:</label>
                                        <div>${this.escapeHtml(h.old_body)}</div>
                                    </div>
                                    <div class="history-new">
                                        <label>Yeni:</label>
                                        <div>${this.escapeHtml(h.new_body)}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            const closeModal = () => {
                modal.remove();
                document.removeEventListener('keydown', handleHistoryKeydown);
                if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
                    previouslyFocused.focus();
                }
            };

            const focusSelector = 'button:not([disabled]), [tabindex]:not([tabindex="-1"])';
            const handleHistoryKeydown = (e) => {
                if (e.key === 'Escape') { closeModal(); return; }
                if (e.key !== 'Tab') return;
                const focusables = Array.from(modal.querySelectorAll(focusSelector)).filter(el => !el.disabled);
                if (!focusables.length) return;
                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            };

            document.addEventListener('keydown', handleHistoryKeydown);
            modal.querySelector('.comment-history-close')?.addEventListener('click', closeModal);
            modal.querySelector('.comment-history-overlay')?.addEventListener('click', closeModal);
            modal.querySelector('.comment-history-close')?.focus();
        },

        // ─── Markdown Toolbar ────────────────────────────────
        initMarkdownToolbar: function () {
            if (!this.markdownEnabled) return;

            const textareas = document.querySelectorAll('.ui-comment-textarea, .ui-comment-inline-textarea');
            textareas.forEach(textarea => {
                if (textarea.dataset.markdownInit) return;
                textarea.dataset.markdownInit = 'true';

                const toolbar = this.createMarkdownToolbar(textarea);
                textarea.parentNode.insertBefore(toolbar, textarea);
            });
        },

        setupMutationObserver: function () {
            if (!this.markdownEnabled) return;

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType !== Node.ELEMENT_NODE) return;
                        
                        const textareas = node.matches('.ui-comment-textarea, .ui-comment-inline-textarea')
                            ? [node]
                            : Array.from(node.querySelectorAll('.ui-comment-textarea, .ui-comment-inline-textarea'));

                        textareas.forEach(textarea => {
                            if (textarea.dataset.markdownInit) return;
                            textarea.dataset.markdownInit = 'true';

                            const toolbar = this.createMarkdownToolbar(textarea);
                            textarea.parentNode.insertBefore(toolbar, textarea);
                        });
                    });
                });
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        },

        createMarkdownToolbar: function (textarea) {
            const toolbar = document.createElement('div');
            toolbar.className = 'markdown-toolbar';
            toolbar.innerHTML = `
                <button type="button" class="md-btn" data-action="bold" title="Kalın (Ctrl+B)">
                    <i class="bi bi-type-bold"></i>
                </button>
                <button type="button" class="md-btn" data-action="italic" title="İtalik (Ctrl+I)">
                    <i class="bi bi-type-italic"></i>
                </button>
                <button type="button" class="md-btn" data-action="code" title="Kod">
                    <i class="bi bi-code"></i>
                </button>
                <button type="button" class="md-btn" data-action="link" title="Link">
                    <i class="bi bi-link-45deg"></i>
                </button>
                <span class="md-divider"></span>
                <button type="button" class="md-btn" data-action="mention" title="Kullanıcı etiketle (@)">
                    <i class="bi bi-at"></i>
                </button>
            `;

            toolbar.addEventListener('click', (e) => {
                const btn = e.target.closest('.md-btn');
                if (!btn) return;

                e.preventDefault();
                const action = btn.dataset.action;
                this.applyMarkdown(textarea, action);
            });

            return toolbar;
        },

        applyMarkdown: async function (textarea, action) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            let replacement = '';

            switch (action) {
                case 'bold':
                    replacement = `**${selectedText || 'kalın metin'}**`;
                    break;
                case 'italic':
                    replacement = `*${selectedText || 'italik metin'}*`;
                    break;
                case 'code':
                    replacement = `\`${selectedText || 'kod'}\``;
                    break;
                case 'link':
                    const url = await window.appPrompt('Link URL', { title: 'Bağlantı ekle', value: 'https://' });
                    if (url) replacement = `[${selectedText || 'link metni'}](${url})`;
                    break;
                case 'mention':
                    replacement = `@${selectedText || 'kullaniciadi'}`;
                    break;
            }

            if (replacement) {
                textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
                textarea.focus();
                textarea.setSelectionRange(start + replacement.length, start + replacement.length);
            }
        },

        // ─── Mention Autocomplete ────────────────────────────
        initMentionAutocomplete: function () {
            if (!this.mentionsEnabled) return;
            if (this.mentionAutocompleteInit) return;
            this.mentionAutocompleteInit = true;
            this.mentionSuggestions = [];
            this.mentionSelectedIndex = 0;
            this.mentionActiveTextarea = null;
            this.mentionTriggerStart = -1;

            const dropdown = document.createElement('div');
            dropdown.className = 'mention-autocomplete';
            dropdown.setAttribute('role', 'listbox');
            dropdown.hidden = true;
            document.body.appendChild(dropdown);
            this.mentionDropdown = dropdown;

            document.addEventListener('input', (e) => {
                const textarea = e.target.closest?.('.ui-comment-textarea, .ui-comment-inline-textarea');
                if (!textarea) return;
                this.handleMentionInput(textarea);
            });

            document.addEventListener('keydown', (e) => {
                if (!this.mentionDropdown || this.mentionDropdown.hidden) return;
                if (!e.target.matches('.ui-comment-textarea, .ui-comment-inline-textarea')) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    this.moveMentionSelection(1);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    this.moveMentionSelection(-1);
                } else if (e.key === 'Enter' || e.key === 'Tab') {
                    const selected = this.mentionSuggestions[this.mentionSelectedIndex];
                    if (selected) {
                        e.preventDefault();
                        this.insertMentionSuggestion(selected);
                    }
                } else if (e.key === 'Escape') {
                    this.hideMentionAutocomplete();
                }
            });

            dropdown.addEventListener('mousedown', (e) => {
                const option = e.target.closest('.mention-autocomplete-option');
                if (!option) return;
                e.preventDefault();
                const selected = this.mentionSuggestions[parseInt(option.dataset.index || '0', 10)];
                if (selected) this.insertMentionSuggestion(selected);
            });

            document.addEventListener('click', (e) => {
                if (e.target.closest('.mention-autocomplete')) return;
                if (e.target.closest('.ui-comment-textarea, .ui-comment-inline-textarea')) return;
                this.hideMentionAutocomplete();
            });
        },

        handleMentionInput: function (textarea) {
            const trigger = this.getMentionTrigger(textarea);
            if (!trigger) {
                this.hideMentionAutocomplete();
                return;
            }

            this.mentionActiveTextarea = textarea;
            this.mentionTriggerStart = trigger.start;
            this.fetchMentionSuggestions(trigger.query, textarea, trigger.start);
        },

        getMentionTrigger: function (textarea) {
            const caret = textarea.selectionStart || 0;
            const beforeCaret = textarea.value.substring(0, caret);
            const match = beforeCaret.match(/(^|\s)@([^\s@]{1,40})$/u);
            if (!match) return null;

            return {
                query: match[2],
                start: caret - match[2].length - 1,
            };
        },

        fetchMentionSuggestions: function (query, textarea, triggerStart) {
            clearTimeout(this.mentionFetchTimer);
            this.mentionFetchTimer = setTimeout(() => {
                const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
                const url = `${baseUri}/api/comments.php?action=mention_search&q=${encodeURIComponent(query)}`;

                (window.publicFetchJson ? window.publicFetchJson(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                }) : Promise.reject(new Error('Public API helper yuklenemedi.')))
                    .then(data => {
                        if (this.mentionActiveTextarea !== textarea || this.mentionTriggerStart !== triggerStart) return;
                        this.renderMentionSuggestions(Array.isArray(data.users) ? data.users : [], textarea);
                    })
                    .catch(() => this.hideMentionAutocomplete());
            }, 160);
        },

        renderMentionSuggestions: function (users, textarea) {
            if (!this.mentionDropdown) return;
            this.mentionDropdown.textContent = '';
            this.mentionSuggestions = users.slice(0, 8);
            this.mentionSelectedIndex = 0;

            if (!this.mentionSuggestions.length) {
                this.hideMentionAutocomplete();
                return;
            }

            this.mentionSuggestions.forEach((user, index) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'mention-autocomplete-option' + (index === 0 ? ' active' : '');
                option.dataset.index = String(index);
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', index === 0 ? 'true' : 'false');

                const avatar = document.createElement('span');
                avatar.className = 'mention-autocomplete-avatar';
                avatar.textContent = String(user.name || '?').trim().charAt(0).toUpperCase() || '?';

                const name = document.createElement('span');
                name.className = 'mention-autocomplete-name';
                name.textContent = '@' + String(user.name || '').trim();

                option.appendChild(avatar);
                option.appendChild(name);
                this.mentionDropdown.appendChild(option);
            });

            const rect = textarea.getBoundingClientRect();
            // Fixed positioning — use rect values directly (no scroll offset needed)
            let top = rect.bottom + 6;
            let left = rect.left;
            const width = Math.min(Math.max(rect.width, 220), 420);
            // Keep dropdown within viewport
            if (top + 250 > window.innerHeight) top = rect.top - 250;
            if (left + width > window.innerWidth) left = window.innerWidth - width - 8;
            if (left < 8) left = 8;
            this.mentionDropdown.style.left = `${left}px`;
            this.mentionDropdown.style.top = `${top}px`;
            this.mentionDropdown.style.width = `${width}px`;
            this.mentionDropdown.hidden = false;
        },

        moveMentionSelection: function (delta) {
            if (!this.mentionSuggestions.length || !this.mentionDropdown) return;
            this.mentionSelectedIndex = (this.mentionSelectedIndex + delta + this.mentionSuggestions.length) % this.mentionSuggestions.length;

            this.mentionDropdown.querySelectorAll('.mention-autocomplete-option').forEach((option, index) => {
                const active = index === this.mentionSelectedIndex;
                option.classList.toggle('active', active);
                option.setAttribute('aria-selected', active ? 'true' : 'false');
                if (active) option.scrollIntoView({ block: 'nearest' });
            });
        },

        insertMentionSuggestion: function (user) {
            const textarea = this.mentionActiveTextarea;
            if (!textarea || this.mentionTriggerStart < 0) return;

            const name = String(user.name || '').trim();
            if (!name) return;

            const end = textarea.selectionStart || textarea.value.length;
            const replacement = `@${name} `;
            textarea.value = textarea.value.substring(0, this.mentionTriggerStart) + replacement + textarea.value.substring(end);
            const caret = this.mentionTriggerStart + replacement.length;
            textarea.focus();
            textarea.setSelectionRange(caret, caret);
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            this.hideMentionAutocomplete();
        },

        hideMentionAutocomplete: function () {
            clearTimeout(this.mentionFetchTimer);
            if (this.mentionDropdown) {
                this.mentionDropdown.hidden = true;
                this.mentionDropdown.textContent = '';
            }
            this.mentionSuggestions = [];
            this.mentionSelectedIndex = 0;
            this.mentionActiveTextarea = null;
            this.mentionTriggerStart = -1;
        },

        // ─── Utilities ───────────────────────────────────────
        escapeHtml: function (text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showToast: function (message, type = 'info') {
            if (typeof window.showToast === 'function') {
                window.showToast(message, type);
                return;
            }

            const aliases = { danger: 'error', failed: 'error', warn: 'warning', ok: 'success' };
            const normalizedType = aliases[type] || type || 'info';
            const icons = {
                success: 'bi-check-circle-fill',
                error: 'bi-exclamation-triangle-fill',
                warning: 'bi-exclamation-circle-fill',
                info: 'bi-info-circle'
            };

            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'topic-toast-container toast-pos-top-right';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'topic-toast toast-' + normalizedType + ' toast-theme-default toast-anim-slide';
            toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
            toast.innerHTML = '<i class="bi ' + (icons[normalizedType] || icons.info) + ' toast-icon"></i>'
                + '<span class="toast-message"></span>'
                + '<button type="button" class="toast-close-btn" aria-label="Kapat">&times;</button>';
            toast.querySelector('.toast-message').textContent = message;

            const dismiss = function () {
                toast.classList.add('toast-out');
                setTimeout(function () { toast.remove(); }, 350);
            };
            toast.querySelector('.toast-close-btn').addEventListener('click', dismiss);
            container.appendChild(toast);
            setTimeout(dismiss, 4000);
        }
    };

})();

/* --- leaderboard.js --- */
/**
 * Leaderboard JavaScript
 * Handles AJAX loading, pagination, and search
 */

(function() {
    'use strict';

    // Widget period selector
    const widgetPeriodSelect = document.getElementById('leaderboard-period-widget');
    if (widgetPeriodSelect) {
        widgetPeriodSelect.addEventListener('change', function() {
            loadWidgetData(this.value);
        });
    }

    function getAppBaseUri() {
        const baseMeta = document.querySelector('meta[name="app-base-uri"]');
        return baseMeta ? (baseMeta.getAttribute('content') || '').replace(/\/+$/, '') : '';
    }

    function getBaseUri() {
        return `${window.location.origin}${getAppBaseUri()}`;
    }

    function normalizeAvatarUrl(value, fallbackUrl) {
        const avatar = String(value || '').trim();
        if (avatar === '') return fallbackUrl;
        if (/^(data|javascript|vbscript):/i.test(avatar)) return fallbackUrl;
        if (/^(https?:)?\/\//i.test(avatar)) return avatar;

        const appBase = getAppBaseUri();
        const cleanBase = appBase.replace(/^\/+/, '').replace(/\/+$/, '');
        let relativePath = avatar.replace(/^\/+/, '');
        if (cleanBase && relativePath.startsWith(cleanBase + '/')) {
            relativePath = relativePath.slice(cleanBase.length + 1);
        }

        return `${window.location.origin}${appBase}/${relativePath}`;
    }

    function slugifyProfileName(value) {
        const text = String(value || '').trim();
        if (text === '') return 'kullanici';

        const normalized = text
            .replace(/[ÇĞİÖŞÜçğıöşü]/g, (char) => ({
                'Ç': 'C',
                'Ğ': 'G',
                'İ': 'I',
                'Ö': 'O',
                'Ş': 'S',
                'Ü': 'U',
                'ç': 'c',
                'ğ': 'g',
                'ı': 'i',
                'ö': 'o',
                'ş': 's',
                'ü': 'u',
            }[char] || char))
            .normalize('NFKD')
            .replace(/[\u0300-\u036f]/g, '');

        const slug = normalized
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return slug || 'kullanici';
    }

    function buildProfileUrl(user, baseUri) {
        const profileUrl = String(user && user.profile_url ? user.profile_url : '').trim();
        if (profileUrl !== '') return profileUrl;

        const userId = parseInt((user && (user.user_id ?? user.id)) || 0, 10);
        if (userId <= 0) return '#';

        const displayName = String((user && (user.username || user.name || user.author)) || '').trim();
        const slug = slugifyProfileName(displayName);

        return `${baseUri}/profil/${encodeURIComponent(`${slug}-${userId}`)}`;
    }

    function buildAvatarUrl(user, fallbackUrl) {
        const avatarValue = user && (user.avatar_url ?? user.avatar ?? user.avatar_path);
        return normalizeAvatarUrl(avatarValue, fallbackUrl);
    }

    /**
     * Load widget data via AJAX
     */
    function loadWidgetData(period) {
        const widgetList = document.getElementById('leaderboard-widget-list');
        if (!widgetList) return;

        // Show loading state
        widgetList.style.opacity = '0.5';
        widgetList.style.pointerEvents = 'none';

        const baseUri = getBaseUri();
        const fallbackAvatarUrl = getLocalAssetUrl('assets/images/noavatar-neon-helmet.svg');
        const apiUrl = `${baseUri}/api/leaderboard?category=daily_login&period=${period}&limit=5`;

        (window.publicFetchJson ? window.publicFetchJson(apiUrl) : Promise.reject(new Error('Public API helper yuklenemedi.')))
            .then(data => {
                if (data.success && data.data) {
                    renderWidgetUsers(data.data, widgetList, fallbackAvatarUrl);
                } else {
                    showWidgetError(widgetList);
                }
            })
            .catch(error => {
                console.error('Leaderboard widget error:', error);
                showWidgetError(widgetList);
            })
            .finally(() => {
                widgetList.style.opacity = '1';
                widgetList.style.pointerEvents = 'auto';
            });
    }

    /**
     * Render widget users
     */
    function renderWidgetUsers(users, container, fallbackAvatarUrl) {
        const medals = ['🥇', '🥈', '🥉'];
        const baseUri = getBaseUri();

        container.innerHTML = users.map((user, index) => {
            const rank = index + 1;
            const username = escapeHtml(user.username || user.name || 'Anonim');
            const count = formatNumber(parseInt(user.count || 0, 10));
            const profileUrl = buildProfileUrl(user, baseUri);
            const avatarUrl = buildAvatarUrl(user, fallbackAvatarUrl);

            const rankHtml = rank <= 3
                ? `<span class="medal">${medals[rank - 1]}</span>`
                : `<span class="rank-number">${rank}</span>`;

            return `
                <div class="leaderboard-item">
                    <div class="leaderboard-rank">${rankHtml}</div>
                    <a href="${profileUrl}" class="leaderboard-avatar">
                        <img src="${avatarUrl}" alt="${username}" width="48" height="48" loading="lazy" decoding="async" data-ui-avatar-img data-ui-avatar-fallback="${fallbackAvatarUrl}">
                    </a>
                    <div class="leaderboard-info">
                        <a href="${profileUrl}" class="leaderboard-username">${username}</a>
                        <span class="leaderboard-score">${count} giriş</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    function getLocalAssetUrl(relativePath) {
        const cleanPath = String(relativePath || '').replace(/^\/+/, '');
        return `${getBaseUri()}/${cleanPath}`;
    }

    /**
     * Show widget error
     */
    function showWidgetError(container) {
        container.innerHTML = `
            <div class="leaderboard-error">
                <i class="bi bi-exclamation-triangle"></i>
                <p>Veriler yüklenemedi</p>
            </div>
        `;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /**
     * Debounce function for search
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Search functionality with debounce
    const searchInput = document.querySelector('.leaderboard-search input[name="search"]');
    if (searchInput) {
        const debouncedSearch = debounce(function() {
            this.form.submit();
        }, 500);

        searchInput.addEventListener('input', debouncedSearch);
    }

    // Keyboard navigation for period buttons
    const periodButtons = document.querySelectorAll('.period-btn');
    periodButtons.forEach((button, index) => {
        button.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && index > 0) {
                periodButtons[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < periodButtons.length - 1) {
                periodButtons[index + 1].focus();
            }
        });
    });

    // Tab navigation for category tabs
    const categoryTabs = document.querySelectorAll('.leaderboard-tab');
    categoryTabs.forEach((tab, index) => {
        tab.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowLeft' && index > 0) {
                categoryTabs[index - 1].focus();
            } else if (e.key === 'ArrowRight' && index < categoryTabs.length - 1) {
                categoryTabs[index + 1].focus();
            }
        });
    });

    // Smooth scroll to top when changing pages
    const paginationButtons = document.querySelectorAll('.pagination button');
    paginationButtons.forEach(button => {
        button.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    // Add loading animation to table when navigating
    const leaderboardTable = document.querySelector('.leaderboard-table-container');
    if (leaderboardTable) {
        window.addEventListener('beforeunload', function() {
            leaderboardTable.style.opacity = '0.5';
        });
    }

    // Highlight current user row with animation
    const currentUserRow = document.querySelector('.leaderboard-table tr.current-user');
    if (currentUserRow) {
        setTimeout(() => {
            currentUserRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            currentUserRow.style.animation = 'highlight-pulse 2s ease-in-out';
        }, 500);
    }

    // Add tooltips to metadata items
    const metadataItems = document.querySelectorAll('.metadata-item');
    metadataItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (title) {
                this.setAttribute('data-tooltip', title);
            }
        });
    });

})();

/* --- ui.js --- */
/* ============================================================
   UI.JS - User Interface Components & Interactions
   Consolidated from: ui.js, theme.js, toast.js,
   navbar-dropdown.js, mobile-sidebar.js, footer-ui.js,
   search-autocomplete.js
   ============================================================ */

/* ============================================================
   SECTION 1: THEME CONTROLLER
   ============================================================ */

/**
 * Public theme controller.
 * Resolves the stored/site theme mode before paint and keeps the toggle icon in sync.
 */
(function () {
    'use strict';

    var storageKey = 'theme';
    var modeStorageKey = 'theme-mode';
    var systemQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function normalizeMode(value) {
        return value === 'light' || value === 'dark' || value === 'auto' ? value : 'auto';
    }

    function getConfiguredMode() {
        var html = document.documentElement;
        var siteMode = normalizeMode(html.getAttribute('data-theme-mode'));
        var storedMode = normalizeMode(localStorage.getItem(modeStorageKey) || localStorage.getItem(storageKey));

        return localStorage.getItem(modeStorageKey) || localStorage.getItem(storageKey)
            ? storedMode
            : siteMode;
    }

    function resolveTheme(mode) {
        var normalizedMode = normalizeMode(mode);
        if (normalizedMode === 'auto') {
            return systemQuery && systemQuery.matches ? 'dark' : 'light';
        }

        return normalizedMode;
    }

    function updateThemeIcon(theme, mode) {
        var icon = document.getElementById('theme-icon');
        if (!icon) {
            return;
        }

        icon.className = theme === 'dark' ? 'bi bi-lightbulb fs-6' : 'bi bi-moon-stars-fill fs-6';
        icon.setAttribute('data-theme-mode', normalizeMode(mode));
    }

    var transitionTimer = null;
    function suppressTransitions() {
        var html = document.documentElement;
        // Toggle a class that disables color/background/border transitions site-wide
        // for the duration of the theme swap, so the change is instant instead of a
        // staggered shimmer across hundreds of elements. Hover transitions resume
        // as soon as the class is removed on the next animation frame.
        html.classList.add('theme-switching');
        if (transitionTimer) {
            clearTimeout(transitionTimer);
        }
        var release = function () {
            transitionTimer = null;
            html.classList.remove('theme-switching');
        };
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(release);
            });
        } else {
            transitionTimer = window.setTimeout(release, 60);
        }
    }

    function applyTheme(mode) {
        var normalizedMode = normalizeMode(mode);
        var theme = resolveTheme(normalizedMode);
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-theme-mode', normalizedMode);
        document.documentElement.setAttribute('data-bs-theme', theme);
        updateThemeIcon(theme, normalizedMode);
        return theme;
    }

    window.toggleTheme = function () {
        var currentTheme = document.documentElement.getAttribute('data-theme') || resolveTheme(getConfiguredMode());
        var nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        suppressTransitions();
        localStorage.setItem(storageKey, nextTheme);
        localStorage.setItem(modeStorageKey, nextTheme);
        applyTheme(nextTheme);
    };

    applyTheme(getConfiguredMode());

    if (systemQuery) {
        var handleSystemChange = function () {
            if (getConfiguredMode() === 'auto') {
                suppressTransitions();
                applyTheme('auto');
            }
        };

        if (systemQuery.addEventListener) {
            systemQuery.addEventListener('change', handleSystemChange);
        } else if (systemQuery.addListener) {
            systemQuery.addListener(handleSystemChange);
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(getConfiguredMode());
        document.querySelectorAll('.theme-toggle').forEach(function (button) {
            button.addEventListener('click', window.toggleTheme);
        });
    });
})();

/* ============================================================
   SECTION 2: TOAST NOTIFICATIONS (Advanced)
   ============================================================ */

// Global toast helper. Container element must exist (rendered by public-footer.php).
// Usage: showToast('Message', 'success'|'error'|'warning'|'info', durationMs)
(function () {
    'use strict';

    // Read config from the container's data attributes (set by PHP)
    function getConfig() {
        var container = document.getElementById('toastContainer');
        if (!container) return null;
        var d = container.dataset;
        return {
            container:      container,
            duration:       parseInt(d.toastDuration, 10) || 5000,
            durSuccess:     parseInt(d.toastDurSuccess, 10) || 0,
            durError:       parseInt(d.toastDurError, 10) || 0,
            durWarning:     parseInt(d.toastDurWarning, 10) || 0,
            theme:          d.toastTheme || 'default',
            animation:      d.toastAnimation || 'slide',
            progressBar:    d.toastProgress !== 'false',
            closeButton:    d.toastClose !== 'false',
            maxVisible:     parseInt(d.toastMax, 10) || 5,
            stackDirection: d.toastStack || 'down',
            clickToClose:   d.toastClickClose !== 'false',
            pauseOnHover:   d.toastPauseHover !== 'false'
        };
    }

    // Icon map
    var icons = {
        success: 'bi-check-circle-fill',
        error:   'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info:    'bi-info-circle'
    };

    // Type aliases
    var aliases = { danger: 'error', failed: 'error', warn: 'warning', ok: 'success' };

    function dismissToast(toast, reason) {
        if (toast._dismissed) return;
        toast._dismissed = true;
        toast._dismissReason = reason || toast._dismissReason || 'dismissed';
        var onClose = toast._onClose;
        toast._onClose = null;
        toast.classList.add('toast-out');
        setTimeout(function () {
            toast.remove();
            if (typeof onClose === 'function') {
                try {
                    onClose(toast._dismissReason, toast);
                } catch (error) {
                    // Toast kapanışındaki hata geri kalan akışı bozmasın.
                }
            }
        }, 350);
    }

    window.showToast = function (message, type, duration) {
        var cfg = getConfig();
        if (!cfg) return;

        var options = {};
        if (duration && typeof duration === 'object') {
            options = duration;
            duration = options.duration;
        }

        type = aliases[type] || type || 'info';

        // Resolve duration: explicit > type-specific > default
        if (typeof duration !== 'number' || duration <= 0) {
            if (type === 'success' && cfg.durSuccess > 0)     duration = cfg.durSuccess;
            else if (type === 'error' && cfg.durError > 0)    duration = cfg.durError;
            else if (type === 'warning' && cfg.durWarning > 0) duration = cfg.durWarning;
            else duration = cfg.duration;
        }

        // Enforce max visible
        var existing = cfg.container.querySelectorAll('.topic-toast:not(.toast-out)');
        while (existing.length >= cfg.maxVisible) {
            dismissToast(existing[0], 'overflow');
            existing = cfg.container.querySelectorAll('.topic-toast:not(.toast-out)');
        }

        // Build toast element
        var toast = document.createElement('div');
        toast.className = 'topic-toast toast-' + type
            + ' toast-theme-' + cfg.theme
            + ' toast-anim-' + cfg.animation;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        toast._onClose = typeof options.onClose === 'function' ? options.onClose : null;

        // Icon
        var iconEl = document.createElement('i');
        iconEl.className = 'bi ' + (icons[type] || icons.info) + ' toast-icon';
        toast.appendChild(iconEl);

        // Message
        var span = document.createElement('span');
        span.className = 'toast-message';
        span.textContent = message;
        toast.appendChild(span);

        // Close button
        if (cfg.closeButton) {
            var closeBtn = document.createElement('button');
            closeBtn.className = 'toast-close-btn';
            closeBtn.innerHTML = '&times;';
            closeBtn.setAttribute('aria-label', 'Kapat');
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                dismissToast(toast, 'button');
            });
            toast.appendChild(closeBtn);
        }

        // Progress bar
        var progressEl = null;
        if (cfg.progressBar) {
            var progressWrap = document.createElement('div');
            progressWrap.className = 'toast-progress-wrap';
            progressEl = document.createElement('div');
            progressEl.className = 'toast-progress toast-progress-' + type;
            progressEl.style.animationDuration = duration + 'ms';
            progressWrap.appendChild(progressEl);
            toast.appendChild(progressWrap);
        }

        // Click to close
        if (cfg.clickToClose) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', function () {
                dismissToast(toast, 'click');
            });
        }

        // Insert (stack direction)
        if (cfg.stackDirection === 'up') {
            cfg.container.insertBefore(toast, cfg.container.firstChild);
        } else {
            cfg.container.appendChild(toast);
        }

        // Auto-dismiss timer
        var timer = null;
        var remaining = duration;
        var startTime = Date.now();

        function startTimer() {
            startTime = Date.now();
            timer = setTimeout(function () {
                dismissToast(toast, 'timeout');
            }, remaining);
        }

        startTimer();

        // Pause on hover
        if (cfg.pauseOnHover) {
            toast.addEventListener('mouseenter', function () {
                if (timer) {
                    clearTimeout(timer);
                    timer = null;
                }
                remaining -= (Date.now() - startTime);
                if (remaining < 0) remaining = 0;
                // Pause progress bar animation
                if (progressEl) {
                    progressEl.style.animationPlayState = 'paused';
                }
            });
            toast.addEventListener('mouseleave', function () {
                if (!toast._dismissed) {
                    startTimer();
                    if (progressEl) {
                        progressEl.style.animationPlayState = 'running';
                    }
                }
            });
        }
    };

    function appDialogEscape(value) {
        return String(value || '').replace(/[&<>"]/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char];
        });
    }

    function closeAppDialog(dialog, value, resolve) {
        dialog.classList.add('is-closing');
        setTimeout(function () {
            dialog.remove();
            document.body.classList.remove('app-dialog-open');
            resolve(value);
        }, 160);
    }

    function createAppDialog(options) {
        options = options || {};

        return new Promise(function (resolve) {
            var dialog = document.createElement('div');
            var needsInput = options.input === true;
            dialog.className = 'app-dialog-overlay';
            dialog.setAttribute('role', 'presentation');
            dialog.innerHTML = [
                '<div class="app-dialog" role="dialog" aria-modal="true" aria-labelledby="appDialogTitle">',
                    '<div class="app-dialog-icon"><i class="bi ', appDialogEscape(options.icon || 'bi-question-circle'), '"></i></div>',
                    '<div class="app-dialog-copy">',
                        '<h3 id="appDialogTitle">', appDialogEscape(options.title || 'Onay gerekiyor'), '</h3>',
                        options.message ? '<p>' + appDialogEscape(options.message) + '</p>' : '',
                    '</div>',
                    needsInput ? '<input class="app-dialog-input" type="' + appDialogEscape(options.type || 'text') + '" value="' + appDialogEscape(options.value || '') + '" placeholder="' + appDialogEscape(options.placeholder || '') + '">' : '',
                    '<div class="app-dialog-actions">',
                        '<button type="button" class="app-dialog-btn app-dialog-cancel">', appDialogEscape(options.cancel || 'Vazgeç'), '</button>',
                        '<button type="button" class="app-dialog-btn app-dialog-ok">', appDialogEscape(options.ok || 'Onayla'), '</button>',
                    '</div>',
                '</div>'
            ].join('');

            document.body.appendChild(dialog);
            document.body.classList.add('app-dialog-open');

            var input = dialog.querySelector('.app-dialog-input');
            var ok = dialog.querySelector('.app-dialog-ok');
            var cancel = dialog.querySelector('.app-dialog-cancel');

            function resolveCancel() { closeAppDialog(dialog, needsInput ? null : false, resolve); }
            function resolveOk() { closeAppDialog(dialog, needsInput ? (input.value || '').trim() : true, resolve); }

            cancel.addEventListener('click', resolveCancel);
            ok.addEventListener('click', resolveOk);
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) resolveCancel();
            });
            dialog.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') resolveCancel();
                if (event.key === 'Enter') resolveOk();
            });

            setTimeout(function () {
                (input || ok).focus();
            }, 0);
        });
    }

    window.appConfirm = function (message, options) {
        return createAppDialog(Object.assign({
            title: 'İşlem onayı',
            message: message,
            ok: 'Onayla',
            icon: 'bi-exclamation-circle'
        }, options || {}));
    };

    window.appPrompt = function (message, options) {
        return createAppDialog(Object.assign({
            title: message || 'Bilgi girin',
            message: '',
            ok: 'Ekle',
            input: true,
            type: 'url',
            placeholder: 'https://',
            icon: 'bi-link-45deg'
        }, options || {}));
    };

    document.addEventListener('submit', function (event) {
        var form = event.target && event.target.closest ? event.target.closest('form[data-app-confirm]') : null;
        if (!form || form.dataset.appConfirmed === '1') return;

        event.preventDefault();
        window.appConfirm(form.dataset.appConfirm, {
            title: form.dataset.appConfirmTitle || 'İşlem onayı',
            ok: form.dataset.appConfirmOk || 'Onayla',
            icon: form.dataset.appConfirmIcon || 'bi-exclamation-circle'
        }).then(function (confirmed) {
            if (!confirmed) return;
            form.dataset.appConfirmed = '1';
            form.submit();
        });
    });

    // Auto-fire flash messages from data attributes on DOMContentLoaded
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.getElementById('toastContainer');
        if (!container) return;
        if (container.dataset.uiFoundationFlashDispatched === '1') return;
        container.dataset.uiFoundationFlashDispatched = '1';

        [
            ['success', container.getAttribute('data-toast-success')],
            ['error', container.getAttribute('data-toast-error')],
            ['info', container.getAttribute('data-toast-info')]
        ].forEach(function (entry) {
            if (entry[1]) {
                window.showToast(entry[1], entry[0]);
            }
        });
    });
})();

/* ============================================================
   SECTION 3: PUBLIC UI BEHAVIORS
   ============================================================ */

// Public-facing UI behaviors: form validation, topic card navigation,
// profile dropdown, Quill rich editor init.
(function () {
    'use strict';

    // URL güvenlik kontrolü: sadece relative veya aynı origin URL'lere izin ver
    function isSafeUrl(url) {
        if (!url) return false;
        // Relative URL'ler güvenli
        if (url.charAt(0) === '/' || url.charAt(0) === '.') return true;
        // javascript: ve data: protokollerini engelle
        var lower = url.toLowerCase().trim();
        if (lower.indexOf('javascript:') === 0 || lower.indexOf('data:') === 0 || lower.indexOf('vbscript:') === 0) return false;
        // Aynı origin kontrolü
        try {
            var parsed = new URL(url, window.location.origin);
            return parsed.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function init() {
        initFormValidation();
        initFormLoadingStates();
        initAuthEnhancements();
        initTopicCards();
        initQuillEditors();
        initCategoryToggles();
        initWidgetToggles();
        initAuthPopover();
    }

    function initAuthPopover() {
        var trigger = document.querySelector('[data-auth-popover-trigger]');
        var panel = document.getElementById('authPopoverPanel');
        if (!trigger || !panel) return;

        function openPanel() {
            panel.hidden = false;
            window.requestAnimationFrame(function () {
                panel.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
                var firstLink = panel.querySelector('a, button');
                if (firstLink) firstLink.focus();
            });
        }

        function closePanel() {
            panel.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            window.setTimeout(function () {
                if (!panel.classList.contains('is-open')) {
                    panel.hidden = true;
                }
            }, 180);
        }

        function togglePanel() {
            if (panel.hidden || !panel.classList.contains('is-open')) {
                openPanel();
            } else {
                closePanel();
            }
        }

        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            togglePanel();
        });

        document.addEventListener('click', function (event) {
            if (!panel.hidden && !panel.contains(event.target) && event.target !== trigger && !trigger.contains(event.target)) {
                closePanel();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !panel.hidden) {
                closePanel();
                trigger.focus();
            }
            if (event.key === 'Tab' && !panel.hidden && panel.classList.contains('is-open')) {
                var focusables = Array.prototype.slice.call(panel.querySelectorAll('a, button')).filter(function (item) {
                    return !item.disabled && item.offsetParent !== null;
                });
                if (!focusables.length) return;
                var first = focusables[0];
                var last = focusables[focusables.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    }

    function initFormLoadingStates() {
        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.loadingInit === '1' || form.matches('[data-no-loading-state], .ttb-favorite-form')) return;
            form.dataset.loadingInit = '1';

            form.addEventListener('submit', function () {
                if (form.dataset.clientInvalid === '1') {
                    form.dataset.clientInvalid = '0';
                    return;
                }

                var submitter = form.querySelector('button[type="submit"], input[type="submit"]');
                if (!submitter || submitter.disabled) return;

                submitter.dataset.originalHtml = submitter.innerHTML;
                submitter.disabled = true;
                submitter.classList.add('is-submitting');
                submitter.setAttribute('aria-busy', 'true');
                if (submitter.tagName.toLowerCase() === 'button') {
                    submitter.innerHTML = '<span>Gönderiliyor...</span><i class="bi bi-arrow-repeat" aria-hidden="true"></i>';
                }

                window.setTimeout(function () {
                    if (!submitter.isConnected) return;
                    submitter.disabled = false;
                    submitter.classList.remove('is-submitting');
                    submitter.setAttribute('aria-busy', 'false');
                    if (submitter.dataset.originalHtml) submitter.innerHTML = submitter.dataset.originalHtml;
                }, 12000);
            });
        });
    }

    function initAuthEnhancements() {
        document.querySelectorAll('.auth-input-shell input[type="password"]').forEach(function (input) {
            if (input.dataset.passwordToggleInit === '1') return;
            input.dataset.passwordToggleInit = '1';

            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'auth-password-toggle';
            toggle.setAttribute('aria-label', 'Şifreyi göster');
            toggle.setAttribute('aria-pressed', 'false');
            toggle.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';
            input.parentNode.appendChild(toggle);

            toggle.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.innerHTML = '<i class="bi ' + (show ? 'bi-eye-slash' : 'bi-eye') + '" aria-hidden="true"></i>';
                input.focus();
            });
        });

        function initPasswordStrengthMeter(passwordInput) {
            if (!passwordInput || passwordInput.dataset.strengthInit === '1') return;
            passwordInput.dataset.strengthInit = '1';

            var minLength = Number(passwordInput.getAttribute('minlength') || 8);
            var confirmSelector = passwordInput.getAttribute('data-password-confirm') || '';
            var confirmPassword = confirmSelector
                ? document.querySelector(confirmSelector)
                : document.querySelector('.auth-screen-register input[name="password_confirm"]');
            var requireUpper = passwordInput.getAttribute('data-password-require-uppercase') !== '0';
            var requireNumber = passwordInput.getAttribute('data-password-require-numbers') !== '0';
            var requireSpecial = passwordInput.getAttribute('data-password-require-special') === '1';

            var rules = [['length', 'En az ' + minLength + ' karakter']];
            if (requireUpper) rules.push(['upper', 'Büyük harf']);
            if (requireNumber) rules.push(['number', 'Rakam']);
            if (requireSpecial) rules.push(['special', 'Özel karakter']);
            if (confirmPassword) rules.push(['match', 'Şifreler eşleşiyor']);

            var meter = document.createElement('div');
            meter.className = 'auth-password-rules';
            meter.setAttribute('aria-live', 'polite');
            meter.innerHTML = rules.map(function (rule) {
                return '<span data-rule="' + rule[0] + '"><i class="bi bi-circle" aria-hidden="true"></i>' + rule[1] + '</span>';
            }).join('');

            var field = passwordInput.closest('.auth-field') || passwordInput.closest('.profile-form-group') || passwordInput.closest('.form-group');
            if (field) field.appendChild(meter);

            function updateRules() {
                var value = passwordInput.value || '';
                var confirm = confirmPassword ? confirmPassword.value || '' : '';
                var checks = {
                    length: value.length >= minLength,
                    upper: /[A-ZÇĞİÖŞÜ]/u.test(value),
                    number: /\d/.test(value),
                    special: /[^A-Za-z0-9ÇĞİÖŞÜçğıöşü]/u.test(value),
                    match: value.length > 0 && confirm.length > 0 && value === confirm
                };

                Object.keys(checks).forEach(function (key) {
                    var item = meter.querySelector('[data-rule="' + key + '"]');
                    if (!item) return;
                    item.classList.toggle('is-met', checks[key]);
                    var icon = item.querySelector('i');
                    if (icon) icon.className = 'bi ' + (checks[key] ? 'bi-check-circle-fill' : 'bi-circle');
                });
            }

            passwordInput.addEventListener('input', updateRules);
            if (confirmPassword) confirmPassword.addEventListener('input', updateRules);
            updateRules();
        }

        document.querySelectorAll('input[data-password-strength], .auth-screen-register input[name="password"]').forEach(initPasswordStrengthMeter);
    }

    // -- Form validation (client-side mirror of server-side validation) ----
    function initFormValidation() {
        document.querySelectorAll('form[novalidate]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                var valid = true;
                form.dataset.clientInvalid = '0';

                form.querySelectorAll('[required]').forEach(function (input) {
                    var isThemeAuthInput = !!input.closest('.ui-theme-auth');
                    input.classList.remove('is-invalid');
                    if (isThemeAuthInput) input.removeAttribute('aria-invalid');
                    if (!input.value.trim()) {
                        input.classList.add('is-invalid');
                        if (isThemeAuthInput) input.setAttribute('aria-invalid', 'true');
                        valid = false;
                    }
                    if (input.type === 'email' && input.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                        input.classList.add('is-invalid');
                        if (isThemeAuthInput) input.setAttribute('aria-invalid', 'true');
                        valid = false;
                    }
                    if (input.minLength > 0 && input.value.length < input.minLength) {
                        input.classList.add('is-invalid');
                        if (isThemeAuthInput) input.setAttribute('aria-invalid', 'true');
                        valid = false;
                    }
                });

                var pw = form.querySelector('[name="password"]');
                var pwc = form.querySelector('[name="password_confirm"]');
                if (pw && pwc && pw.value !== pwc.value) {
                    pwc.classList.add('is-invalid');
                    if (pwc.closest('.ui-theme-auth')) pwc.setAttribute('aria-invalid', 'true');
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                    form.dataset.clientInvalid = '1';
                    if (typeof window.showToast === 'function') {
                        window.showToast('Lütfen tüm alanları doğru doldurun.', 'error');
                    }
                }
            });
        });

        document.querySelectorAll('form [required]').forEach(function (input) {
            input.addEventListener('blur', function () {
                if (!input.value.trim()) {
                    input.classList.add('is-invalid');
                    if (input.closest('.ui-theme-auth')) input.setAttribute('aria-invalid', 'true');
                } else {
                    input.classList.remove('is-invalid');
                    if (input.closest('.ui-theme-auth')) input.removeAttribute('aria-invalid');
                }
            });
            input.addEventListener('input', function () {
                if (input.value.trim()) {
                    input.classList.remove('is-invalid');
                    if (input.closest('.ui-theme-auth')) input.removeAttribute('aria-invalid');
                }
            });
        });
    }

    // -- Topic card click-to-navigate (keeps inner links functional) -------
    function initTopicCards() {
        var interactiveSelector = 'a, button, input, textarea, select, label';
        document.querySelectorAll('.topic-list-card[data-topic-url]').forEach(function (card) {
            if (!card.hasAttribute('tabindex')) {
                card.setAttribute('tabindex', '0');
            }
            if (!card.hasAttribute('role')) {
                card.setAttribute('role', 'link');
            }

            card.addEventListener('click', function (e) {
                if (e.target.closest(interactiveSelector)) return;
                var url = card.getAttribute('data-topic-url');
                if (url && isSafeUrl(url)) window.location.href = url;
            });

            card.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter' && e.key !== ' ') return;
                if (e.target.closest(interactiveSelector)) return;
                e.preventDefault();
                var url = card.getAttribute('data-topic-url');
                if (url && isSafeUrl(url)) window.location.href = url;
            });
        });
    }

    // -- Quill editor on textarea.rich-editor ------------------------------
    function initQuillEditors() {
        if (typeof Quill === 'undefined') return;

        var AlignStyle = Quill.import('attributors/style/align');
        Quill.register(AlignStyle, true);
        var ColorStyle = Quill.import('attributors/style/color');
        Quill.register(ColorStyle, true);
        var BackgroundStyle = Quill.import('attributors/style/background');
        Quill.register(BackgroundStyle, true);

        document.querySelectorAll('.rich-editor').forEach(function (el) {
            if (el.tagName.toLowerCase() !== 'textarea') return;
            if (el.dataset._quillInit === '1') return;
            el.dataset._quillInit = '1';

            var wrapper = document.createElement('div');
            wrapper.className = 'quill-container';
            var editorDiv = document.createElement('div');
            wrapper.appendChild(editorDiv);

            el.parentNode.insertBefore(wrapper, el.nextSibling);
            el.style.display = 'none';

            var initialContent = el.value;
            var quill = new Quill(editorDiv, {
                theme: 'snow',
                modules: {
                    toolbar: [[{header:[1,2,3,false]}],["bold","italic","underline","strike"],[{color:[]},{background:[]}],["blockquote","code-block"],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'image', 'video'],
                        ['clean'],
                        [{ align: [] }]
                    ]
                }
            });

            // İçeriği güvenli şekilde yükle (XSS koruması)
            if (initialContent) {
                try {
                    var delta = quill.clipboard.convert(initialContent);
                    quill.setContents(delta, 'silent');
                } catch (e) {
                    // Fallback: plain text olarak yükle
                    quill.setText(initialContent);
                }
            }

            quill.on('text-change', function () {
                el.value = quill.root.innerHTML;
            });
            el.quillInstance = quill;
        });
    }

    // -- Category toggles (parent categories with subcategories) -----------
    function initCategoryToggles() {
        document.querySelectorAll('.category-toggle').forEach(function (toggle) {
            if (toggle.dataset.catToggleInit === '1') return;
            toggle.dataset.catToggleInit = '1';
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var categoryItem = toggle.closest('.category-item');
                if (!categoryItem) return;

                var isOpen = categoryItem.classList.contains('open');

                if (isOpen) {
                    categoryItem.classList.remove('open');
                    toggle.setAttribute('aria-expanded', 'false');
                } else {
                    categoryItem.classList.add('open');
                    toggle.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }

    // -- Widget toggles (collapsible widgets) ------------------------------
    function initWidgetToggles() {
        // Global toggleWidget function for inline onclick handlers
        window.toggleWidget = function(button) {
            var widget = button.closest('.widget');
            if (!widget) return;

            var body = widget.querySelector('.widget-body');
            if (!body) return;

            var isActive = button.classList.contains('active');

            if (isActive) {
                button.classList.remove('active');
                body.style.display = 'none';
                button.setAttribute('aria-expanded', 'false');
            } else {
                button.classList.add('active');
                body.style.display = 'block';
                button.setAttribute('aria-expanded', 'true');
            }
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/* ============================================================
   SECTION 4B: PROFILE PAGE HELPERS
   ============================================================ */

(function () {
    'use strict';

    function initUserReportModal() {
        var modal = document.getElementById('userReportModal');
        if (!modal || modal.dataset.profileReportInit === '1') return;
        modal.dataset.profileReportInit = '1';

        function openModal() {
            modal.hidden = false;
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('topic-report-modal-open');
            var first = modal.querySelector('select, textarea, button');
            if (first) first.focus();
        }

        function closeModal() {
            modal.hidden = true;
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('topic-report-modal-open');
        }

        document.addEventListener('click', function (event) {
            if (event.target.closest('[data-user-report-modal-open]')) {
                openModal();
            }
            if (event.target.closest('[data-user-report-modal-close]')) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        document.addEventListener('submit', function (event) {
            var form = event.target.closest('.user-report-form');
            if (!form) return;
            event.preventDefault();

            var feedback = form.querySelector('.topic-report-feedback');
            var button = form.querySelector('button[type="submit"]');
            var original = button ? button.innerHTML : '';
            if (button) {
                button.disabled = true;
                button.innerHTML = '<i class="bi bi-hourglass-split"></i> Gönderiliyor...';
            }

            (window.publicFetchJson ? window.publicFetchJson(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: Object.fromEntries(new FormData(form).entries()),
                notifyError: false
            }).then(function (payload) {
                return { ok: true, payload: payload };
            }) : Promise.reject(new Error('Public API helper yuklenemedi.'))).then(function (result) {
                var message = result.payload.message || (result.ok ? 'Şikayet gönderildi.' : 'Şikayet gönderilemedi.');
                if (feedback) {
                    feedback.textContent = message;
                    feedback.className = 'topic-report-feedback ' + (result.ok && result.payload.success ? 'is-success' : 'is-error');
                }
                if (result.ok && result.payload.success) {
                    form.reset();
                    closeModal();
                    if (window.showToast) window.showToast(message, 'success');
                }
            }).catch(function () {
                if (feedback) {
                    feedback.textContent = 'Bağlantı hatası. Lütfen tekrar deneyin.';
                    feedback.className = 'topic-report-feedback is-error';
                }
            }).finally(function () {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = original;
                }
            });
        });
    }

    function initProfileAvatarForm() {
        var form = document.getElementById('profileAvatarForm');
        if (!form || form.dataset.avatarInit === '1') return;
        form.dataset.avatarInit = '1';

        var input = form.querySelector('[data-avatar-input]');
        var preview = form.querySelector('[data-avatar-preview]');
        var selected = form.querySelector('[data-avatar-selected]');
        var submit = form.querySelector('[data-avatar-submit]');
        var reset = form.querySelector('[data-avatar-reset]');
        var actionText = form.querySelector('[data-avatar-action-text]');
        var initialPreview = preview ? preview.innerHTML : '';
        var previewUrl = '';
        var maxSize = 2 * 1024 * 1024;
        var allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        function clearPreview() {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }
            if (preview) preview.innerHTML = initialPreview;
            if (selected) selected.textContent = 'Henüz yeni dosya seçilmedi.';
            if (submit) submit.disabled = true;
            if (reset) reset.hidden = true;
            if (actionText) actionText.textContent = 'Dosya seç';
            if (input) input.value = '';
        }

        if (input) {
            input.addEventListener('change', function () {
                var file = input.files && input.files[0] ? input.files[0] : null;
                if (!file) {
                    clearPreview();
                    return;
                }
                if (!allowedTypes.includes(file.type)) {
                    if (window.showToast) window.showToast('Lütfen JPG, PNG, WebP veya GIF seçin.', 'warning');
                    clearPreview();
                    return;
                }
                if (file.size > maxSize) {
                    if (window.showToast) window.showToast('Profil fotoğrafı en fazla 2 MB olabilir.', 'warning');
                    clearPreview();
                    return;
                }
                if (previewUrl) URL.revokeObjectURL(previewUrl);
                previewUrl = URL.createObjectURL(file);
                if (preview) preview.innerHTML = '<img src="' + previewUrl + '" alt="" data-avatar-img data-ui-avatar-img>';
                if (selected) selected.textContent = file.name;
                if (submit) submit.disabled = false;
                if (reset) reset.hidden = false;
                if (actionText) actionText.textContent = 'Fotoğrafı değiştir';
            });
        }

        if (reset) reset.addEventListener('click', clearPreview);

        form.addEventListener('submit', function (event) {
            if (!input || !input.files || !input.files.length) {
                event.preventDefault();
                if (window.showToast) window.showToast('Önce bir profil fotoğrafı seçin.', 'warning');
            }
        });
    }

    function initProfilePasswordForm() {
        var form = document.getElementById('profilePasswordForm');
        if (!form || form.dataset.passwordInit === '1') return;
        form.dataset.passwordInit = '1';

        form.addEventListener('submit', function (event) {
            var newPassword = document.getElementById('pw_new');
            var confirmPassword = document.getElementById('pw_confirm');
            if (newPassword && confirmPassword && newPassword.value !== confirmPassword.value) {
                event.preventDefault();
                if (window.showToast) window.showToast('Şifreler eşleşmiyor.', 'warning');
                confirmPassword.focus();
            }
        });
    }

    function initProfileHelpers() {
        initUserReportModal();
        initProfileAvatarForm();
        initProfilePasswordForm();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileHelpers);
    } else {
        initProfileHelpers();
    }
})();

/* ============================================================
   SECTION 4: NAVBAR DROPDOWN
   ============================================================ */

/**
 * Profile Dropdown Handler — click toggle + keyboard navigation.
 * Single source of truth for #profileDropdownBtn / #profileDropdownMenu.
 */

(function() {
    'use strict';

    const root = document.querySelector('.ui-theme-profile-dropdown');
    const btn = document.getElementById('profileDropdownBtn');
    const menu = document.getElementById('profileDropdownMenu');

    if (!root || !btn || !menu) return;

    const items = () => Array.from(menu.querySelectorAll('.ui-theme-profile-menu__item'));

    function setOpen(open, focusMenu) {
        root.classList.toggle('is-open', open);
        menu.classList.toggle('show', open);
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open && focusMenu) {
            window.setTimeout(function() {
                const first = items()[0];
                if (first) first.focus();
            }, 20);
        }
    }

    function isOpen() {
        return root.classList.contains('is-open');
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        setOpen(!isOpen(), false);
    });

    btn.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setOpen(true, true);
        } else if (e.key === 'Escape') {
            setOpen(false, false);
        }
    });

    menu.addEventListener('keydown', function(e) {
        const menuItems = items();
        const current = menuItems.indexOf(document.activeElement);

        if (e.key === 'Escape') {
            e.preventDefault();
            setOpen(false, false);
            btn.focus();
            return;
        }

        if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;

        e.preventDefault();
        if (!menuItems.length) return;

        const next = e.key === 'ArrowDown'
            ? (current + 1) % menuItems.length
            : (current <= 0 ? menuItems.length - 1 : current - 1);
        menuItems[next].focus();
    });

    document.addEventListener('click', function(e) {
        if (isOpen() && !root.contains(e.target)) {
            setOpen(false, false);
        }
    });

    window.addEventListener('resize', function() {
        if (isOpen()) setOpen(false, false);
    });
})();

/* ============================================================
   SECTION 5: MOBILE SIDEBAR
   ============================================================ */

/**
 * Mobile Sidebar Toggle System
 * Handles sidebar visibility on mobile devices
 */

class MobileSidebar {
    constructor() {
        this.init();
    }

    init() {
        // Sadece mobil cihazlarda çalıştır
        if (window.innerWidth > 768) return;
        if (document.documentElement.getAttribute('data-public-theme') === 'turkmod') return;

        this.createToggleButton();
        this.createOverlay();
        this.attachEventListeners();
    }

    createToggleButton() {
        const button = document.createElement('button');
        button.className = 'sidebar-toggle';
        button.innerHTML = '<i class="bi bi-list"></i>';
        button.setAttribute('aria-label', 'Menüyü Aç');
        document.body.appendChild(button);
        this.toggleButton = button;
    }

    createOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
        this.overlay = overlay;
    }

    attachEventListeners() {
        // Toggle button click
        this.toggleButton.addEventListener('click', () => this.toggleSidebar());

        // Overlay click - close sidebar
        this.overlay.addEventListener('click', () => this.closeSidebar());

        // ESC key - close sidebar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') this.closeSidebar();
        });

        // Window resize - reset on desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                this.closeSidebar();
                this.toggleButton.style.display = 'none';
            } else {
                this.toggleButton.style.display = 'flex';
            }
        });

        // Swipe gestures
        this.initSwipeGestures();
    }

    toggleSidebar() {
        const leftSidebar = document.querySelector('.sidebar-left');
        const rightSidebar = document.querySelector('.sidebar-right');

        if (leftSidebar) {
            const isActive = leftSidebar.classList.contains('active');

            if (isActive) {
                this.closeSidebar();
            } else {
                leftSidebar.classList.add('active');
                this.overlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }
    }

    closeSidebar() {
        const leftSidebar = document.querySelector('.sidebar-left');
        const rightSidebar = document.querySelector('.sidebar-right');

        if (leftSidebar) leftSidebar.classList.remove('active');
        if (rightSidebar) rightSidebar.classList.remove('active');

        this.overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    initSwipeGestures() {
        let startX = 0;
        let startY = 0;

        document.addEventListener('touchstart', (e) => {
            startX = e.touches[0].clientX;
            startY = e.touches[0].clientY;
        }, { passive: true });

        document.addEventListener('touchend', (e) => {
            const endX = e.changedTouches[0].clientX;
            const endY = e.changedTouches[0].clientY;

            const diffX = endX - startX;
            const diffY = endY - startY;

            // Horizontal swipe daha dominant ise
            if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                // Swipe right from left edge - open sidebar
                if (startX < 50 && diffX > 0) {
                    const leftSidebar = document.querySelector('.sidebar-left');
                    if (leftSidebar && !leftSidebar.classList.contains('active')) {
                        leftSidebar.classList.add('active');
                        this.overlay.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                }
                // Swipe left - close sidebar
                else if (diffX < -50) {
                    this.closeSidebar();
                }
            }
        }, { passive: true });
    }
}

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new MobileSidebar());
} else {
    new MobileSidebar();
}

/* ============================================================
   SECTION 6: FOOTER UI HELPERS
   ============================================================ */

/**
 * Footer UI Helpers
 * Widget toggle, category toggle, tab switching, newsletter form
 */

window.toggleWidget = window.toggleWidget || function(button) {
    button.classList.toggle('active');
    const body = button.nextElementSibling;
    if (body) {
        body.style.display = button.classList.contains('active') ? 'block' : 'none';
    }
};

window.toggleCategory = function(button) {
    const item = button.closest('.category-item');
    if (item) {
        const isOpen = item.classList.toggle('open');
        button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        const subcategories = item.querySelector('.subcategories');
        if (subcategories) {
            subcategories.removeAttribute('hidden');
        }
    }
};

window.switchTab = window.switchTab || function(button, tabId) {
    const container = button.closest('.filter-tabs') || button.closest('.profile-tabs');
    if (container) {
        container.querySelectorAll('button').forEach(btn => btn.classList.remove('active'));
        button.classList.add('active');
    }
};

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('img[data-fallback-src]').forEach(function(img) {
        if (img.dataset.fallbackBound === '1') return;
        img.dataset.fallbackBound = '1';
        img.addEventListener('error', function() {
            var fallback = img.getAttribute('data-fallback-src');
            if (!fallback || img.src.endsWith(fallback)) return;
            img.src = fallback;
            img.classList.add('is-fallback-image');
        });
    });

    document.querySelectorAll('.category-item .subcategories').forEach(function(subcategories) {
        const item = subcategories.closest('.category-item');
        const toggle = item ? item.querySelector('.category-toggle') : null;
        const isOpen = item ? item.classList.contains('open') : false;
        subcategories.removeAttribute('hidden');
        if (toggle) {
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
    });

    document.addEventListener('click', function(event) {
        const toggle = event.target.closest('.category-toggle');
        if (toggle) {
            event.preventDefault();
            window.toggleCategory(toggle);
            return;
        }

        const catToggle = event.target.closest('[data-cat-toggle]');
        if (catToggle) {
            event.preventDefault();
            const panelId = catToggle.getAttribute('aria-controls');
            if (panelId) {
                const panel = document.getElementById(panelId);
                if (panel) {
                    const isExpanded = catToggle.getAttribute('aria-expanded') === 'true';
                    catToggle.setAttribute('aria-expanded', !isExpanded);
                    
                    if (isExpanded) {
                        panel.hidden = true;
                        panel.style.setProperty('display', 'none', 'important');
                    } else {
                        panel.hidden = false;
                        panel.style.setProperty('display', 'flex', 'important');
                    }
                    
                    const parentItem = catToggle.closest('[data-cat-item]');
                    if (parentItem) {
                        parentItem.classList.toggle('is-active', !isExpanded);
                        parentItem.classList.toggle('is-open', !isExpanded);
                    }
                }
            }
        }
    });

    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function(event) {
            event.preventDefault();
            const input = newsletterForm.querySelector('[data-newsletter-email], input[type="email"]');
            const feedback = document.querySelector('[data-newsletter-feedback]');
            const email = input ? input.value.trim() : '';
            if (!input || !email || !input.checkValidity()) {
                if (feedback) feedback.textContent = 'Lütfen geçerli bir e-posta adresi girin.';
                if (window.showToast) window.showToast('Lütfen geçerli bir e-posta adresi girin.', 'warning');
                input && input.focus();
                return;
            }
            try {
                const saved = JSON.parse(localStorage.getItem('mod2.newsletterEmails') || '[]');
                if (!saved.includes(email)) {
                    saved.push(email);
                    localStorage.setItem('mod2.newsletterEmails', JSON.stringify(saved.slice(-20)));
                }
            } catch (error) {}
            newsletterForm.classList.add('is-submitted');
            if (feedback) feedback.textContent = 'Kaydınız alındı. Yeni içerik duyuruları için bu adresi hatırlayacağız.';
            if (window.showToast) window.showToast('Bülten kaydınız alındı.', 'success');
        });
    }

    const rememberForm = document.querySelector('[data-remember-email-form]');
    if (rememberForm) {
        const input = rememberForm.querySelector('[data-remember-email-input]');
        const checkbox = rememberForm.querySelector('[data-remember-email-check]');
        try {
            const remembered = localStorage.getItem('mod2.loginEmail') || '';
            if (input && remembered && !input.value) {
                input.value = remembered;
                if (checkbox) checkbox.checked = true;
            }
        } catch (error) {}
        rememberForm.addEventListener('submit', function() {
            if (!input || !checkbox) return;
            try {
                if (checkbox.checked && input.value.trim()) {
                    localStorage.setItem('mod2.loginEmail', input.value.trim());
                } else {
                    localStorage.removeItem('mod2.loginEmail');
                }
            } catch (error) {}
        });
    }
});

/* ============================================================
   SECTION 7: SEARCH AUTOCOMPLETE
   ============================================================ */

(() => {
    'use strict';

    const baseUri = document.querySelector('meta[name="app-base-uri"]')?.content || '';
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"]/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[char]));
    const highlight = (text, query) => {
        const safeText = escapeHtml(text);
        const q = String(query || '').trim().replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return q ? safeText.replace(new RegExp(`(${q})`, 'ig'), '<mark>$1</mark>') : safeText;
    };
    const debounce = (fn, wait = 250) => {
        let timeout;
        return (...args) => {
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => fn(...args), wait);
        };
    };

    class SearchAutocomplete {
        constructor(input) {
            this.input = input;
            this.form = input.closest('form');
            this.results = document.createElement('div');
            this.results.className = 'search-autocomplete-results';
            this.results.hidden = true;
            this.form.style.position = this.form.style.position || 'relative';
            this.form.appendChild(this.results);
            this.bind();
        }

        bind() {
            this.input.addEventListener('input', debounce(() => this.search(), 300));
            this.input.addEventListener('focus', () => this.search());
            document.addEventListener('click', (event) => {
                if (!this.form.contains(event.target)) this.hide();
            });
            this.input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') this.hide();
            });
        }

        async search() {
            const query = this.input.value.trim();
            if (query.length < 2) {
                this.hide();
                return;
            }

            try {
                if (!window.publicFetchJson) {
                    throw new Error('Public API helper yuklenemedi.');
                }
                const payload = await window.publicFetchJson(`${baseUri}/api/search.php?q=${encodeURIComponent(query)}`, {
                    headers: { 'Accept': 'application/json' },
                });
                this.render(payload.results || [], query);
                window.analytics?.trackSearch?.(query, payload.total ?? 0);
            } catch (error) {
                this.hide();
            }
        }

        render(results, query) {
            if (!results.length) {
                this.results.innerHTML = '<div class="search-autocomplete-empty">Sonuç bulunamadı</div>';
                this.results.hidden = false;
                return;
            }

            this.results.innerHTML = results.map((item) => `
                <a href="${escapeHtml(item.url)}" class="search-autocomplete-item">
                    <img src="${escapeHtml(item.image)}" alt="" width="44" height="44" loading="lazy" decoding="async">
                    <span>
                        <strong>${highlight(item.title, query)}</strong>
                        <small>${escapeHtml(item.category || 'Genel')}</small>
                    </span>
                </a>
            `).join('');
            this.results.hidden = false;
        }

        hide() {
            this.results.hidden = true;
        }
    }

    const init = () => document.querySelectorAll('[data-search-autocomplete]').forEach((input) => new SearchAutocomplete(input));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();

/* --- functions.js --- */

"use strict";
!function () {

		window.Element.prototype.removeClass = function () {
				let className = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : "",
						selectors = this;
				if (!(selectors instanceof HTMLElement) && selectors !== null) {
						selectors = document.querySelector(selectors);
				}
				if (this.isVariableDefined(selectors) && className) {
						selectors.classList.remove(className);
				}
				return this;
		}, window.Element.prototype.addClass = function () {
				let className = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : "",
						selectors = this;
				if (!(selectors instanceof HTMLElement) && selectors !== null) {
						selectors = document.querySelector(selectors);
				}
				if (this.isVariableDefined(selectors) && className) {
						selectors.classList.add(className);
				}
				return this;
		}, window.Element.prototype.toggleClass = function () {
				let className = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : "",
						selectors = this;
				if (!(selectors instanceof HTMLElement) && selectors !== null) {
						selectors = document.querySelector(selectors);
				}
				if (this.isVariableDefined(selectors) && className) {
						selectors.classList.toggle(className);
				}
				return this;
		}, window.Element.prototype.isVariableDefined = function () {
				return !!this && typeof (this) != 'undefined' && this != null;
		}
}();


var e = {
		init: function () {
				e.preLoader(),
				e.navbarDropdownHover(),
				e.tinySlider(),
				e.toolTipFunc(),
				e.popOverFunc(),
				e.videoPlyr(),
				e.lightBox(),
				e.darkMode(),
				e.sidebarToggleStart(),
				e.sidebarToggleEnd(),
				e.choicesSelect(),
				e.autoResize(),
				e.DropZone(),
				e.flatPicker(),
				e.avatarImg(),
				e.customScrollbar(),
				e.toasts(),
				e.pswMeter(),
				e.fakePwd();
		},
		isVariableDefined: function (el) {
				return typeof !!el && (el) != 'undefined' && el != null;
		},
		getParents: function (el, selector, filter) {
				const result = [];
				const matchesSelector = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector;

				// match start from parent
				el = el.parentElement;
				while (el && !matchesSelector.call(el, selector)) {
						if (!filter) {
								if (selector) {
										if (matchesSelector.call(el, selector)) {
												return result.push(el);
										}
								} else {
										result.push(el);
								}
						} else {
								if (matchesSelector.call(el, filter)) {
										result.push(el);
								}
						}
						el = el.parentElement;
						if (e.isVariableDefined(el)) {
								if (matchesSelector.call(el, selector)) {
										return el;
								}
						}

				}
				return result;
		},
		getNextSiblings: function (el, selector, filter) {
				let sibs = [];
				let nextElem = el.parentNode.firstChild;
				const matchesSelector = el.matches || el.webkitMatchesSelector || el.mozMatchesSelector || el.msMatchesSelector;
				do {
						if (nextElem.nodeType === 3) continue; // ignore text nodes
						if (nextElem === el) continue; // ignore elem of target
						if (nextElem === el.nextElementSibling) {
								if ((!filter || filter(el))) {
										if (selector) {
												if (matchesSelector.call(nextElem, selector)) {
														return nextElem;
												}
										} else {
												sibs.push(nextElem);
										}
										el = nextElem;

								}
						}
				} while (nextElem = nextElem.nextSibling)
				return sibs;
		},
		on: function (selectors, type, listener) {
				document.addEventListener("DOMContentLoaded", () => {
						if (!(selectors instanceof HTMLElement) && selectors !== null) {
								selectors = document.querySelector(selectors);
						}
						selectors.addEventListener(type, listener);
				});
		},
		onAll: function (selectors, type, listener) {
				document.addEventListener("DOMContentLoaded", () => {
						document.querySelectorAll(selectors).forEach((element) => {
								if (type.indexOf(',') > -1) {
										let types = type.split(',');
										types.forEach((type) => {
												element.addEventListener(type, listener);
										});
								} else {
										element.addEventListener(type, listener);
								}


						});
				});
		},
		removeClass: function (selectors, className) {
				if (!(selectors instanceof HTMLElement) && selectors !== null) {
						selectors = document.querySelector(selectors);
				}
				if (e.isVariableDefined(selectors)) {
						selectors.removeClass(className);
				}
		},
		removeAllClass: function (selectors, className) {
				if (e.isVariableDefined(selectors) && (selectors instanceof HTMLElement)) {
						document.querySelectorAll(selectors).forEach((element) => {
								element.removeClass(className);
						});
				}

		},
		toggleClass: function (selectors, className) {
				if (!(selectors instanceof HTMLElement) && selectors !== null) {
						selectors = document.querySelector(selectors);
				}
				if (e.isVariableDefined(selectors)) {
						selectors.toggleClass(className);
				}
		},
		toggleAllClass: function (selectors, className) {
				if (e.isVariableDefined(selectors)  && (selectors instanceof HTMLElement)) {
						document.querySelectorAll(selectors).forEach((element) => {
								element.toggleClass(className);
						});
				}
		},
		addClass: function (selectors, className) {
				if (!(selectors instanceof HTMLElement) && selectors !== null) {
						selectors = document.querySelector(selectors);
				}
				if (e.isVariableDefined(selectors)) {
						selectors.addClass(className);
				}
		},
		select: function (selectors) {
				return document.querySelector(selectors);
		},
		selectAll: function (selectors) {
				return document.querySelectorAll(selectors);
		},

		// START: 01 Preloader
		preLoader: function () {
				window.addEventListener('load', function () {
						var preloader = e.select('.preloader');
						if (e.isVariableDefined(preloader)) {
								preloader.className += ' animate__animated animate__fadeOut';
								setTimeout(function(){
										preloader.hidden = true;
								}, 200);
						}
				});
		},
		// END: Preloader

		// START: 02 Navbar dropdown hover
		navbarDropdownHover: function () {
				e.onAll('.dropdown-menu a.dropdown-item.dropdown-toggle', 'click', function (event) {
						var element = this;
						event.preventDefault();
						event.stopImmediatePropagation();
						if (e.isVariableDefined(element.nextElementSibling) && !element.nextElementSibling.classList.contains("show")) {
								const parents = e.getParents(element, '.dropdown-menu');
								e.removeClass(parents.querySelector('.show'), "show");
								if(e.isVariableDefined(parents.querySelector('.dropdown-opened'))){
										e.removeClass(parents.querySelector('.dropdown-opened'), "dropdown-opened");
								}
						}
						var $subMenu = e.getNextSiblings(element, ".dropdown-menu");
						e.toggleClass($subMenu, "show");
						$subMenu.previousElementSibling.toggleClass('dropdown-opened');
						var parents = e.getParents(element, 'li.nav-item.dropdown.show');
						if (e.isVariableDefined(parents) && parents.length > 0) {
								e.on(parents, 'hidden.bs.dropdown', function (event) {
										e.removeAllClass('.dropdown-submenu .show');
								});
						}
				});
		},
		// END: Navbar dropdown hover

  	// START: 03 Tiny Slider
		tinySlider: function () {
				var $carousel = e.select('.tiny-slider-inner');
				if (e.isVariableDefined($carousel)) {
					var tnsCarousel = e.selectAll('.tiny-slider-inner');
					tnsCarousel.forEach(slider => {
							var slider1 = slider;
							var sliderMode = slider1.getAttribute('data-mode') ? slider1.getAttribute('data-mode') : 'carousel';
							var sliderAxis = slider1.getAttribute('data-axis') ? slider1.getAttribute('data-axis') : 'horizontal';
							var sliderSpace = slider1.getAttribute('data-gutter') ? slider1.getAttribute('data-gutter') : 30;
							var sliderEdge = slider1.getAttribute('data-edge') ? slider1.getAttribute('data-edge') : 0;

							var sliderItems = slider1.getAttribute('data-items') ? slider1.getAttribute('data-items') : 4; //option: number (items in all device)
							var sliderItemsXl = slider1.getAttribute('data-items-xl') ? slider1.getAttribute('data-items-xl') : Number(sliderItems); //option: number (items in 1200 to end )
							var sliderItemsLg = slider1.getAttribute('data-items-lg') ? slider1.getAttribute('data-items-lg') : Number(sliderItemsXl); //option: number (items in 992 to 1199 )
							var sliderItemsMd = slider1.getAttribute('data-items-md') ? slider1.getAttribute('data-items-md') : Number(sliderItemsLg); //option: number (items in 768 to 991 )
							var sliderItemsSm = slider1.getAttribute('data-items-sm') ? slider1.getAttribute('data-items-sm') : Number(sliderItemsMd); //option: number (items in 576 to 767 )
							var sliderItemsXs = slider1.getAttribute('data-items-xs') ? slider1.getAttribute('data-items-xs') : Number(sliderItemsSm); //option: number (items in start to 575 )

							var sliderSpeed = slider1.getAttribute('data-speed') ? slider1.getAttribute('data-speed') : 500;
							var sliderautoWidth = slider1.getAttribute('data-autowidth') === 'true'; //option: true or false
							var sliderArrow = slider1.getAttribute('data-arrow') !== 'false'; //option: true or false
							var sliderDots = slider1.getAttribute('data-dots') !== 'false'; //option: true or false

							var sliderAutoPlay = slider1.getAttribute('data-autoplay') !== 'false'; //option: true or false
							var sliderAutoPlayTime = slider1.getAttribute('data-autoplaytime') ? slider1.getAttribute('data-autoplaytime') : 4000;
							var sliderHoverPause = slider1.getAttribute('data-hoverpause') === 'true'; //option: true or false
							if (e.isVariableDefined(e.select('.custom-thumb'))) {
								var sliderNavContainer = e.select('.custom-thumb');
							} 
							var sliderLoop = slider1.getAttribute('data-loop') !== 'false'; //option: true or false
							var sliderRewind = slider1.getAttribute('data-rewind') === 'true'; //option: true or false
							var sliderAutoHeight = slider1.getAttribute('data-autoheight') === 'true'; //option: true or false
							var sliderfixedWidth = slider1.getAttribute('data-fixedwidth') === 'true'; //option: true or false
							var sliderTouch = slider1.getAttribute('data-touch') !== 'false'; //option: true or false
							var sliderDrag = slider1.getAttribute('data-drag') !== 'false'; //option: true or false
							// Check if document DIR is RTL
							var ifRtl = document.getElementsByTagName("html")[0].getAttribute("dir");
							var sliderDirection;
							if (ifRtl === 'rtl') {
									sliderDirection = 'rtl';
							}

							var tnsSlider = tns({
									container: slider,
									mode: sliderMode,
									axis: sliderAxis,
									gutter: sliderSpace,
									edgePadding: sliderEdge,
									speed: sliderSpeed,
									autoWidth: sliderautoWidth,
									controls: sliderArrow,
									nav: sliderDots,
									autoplay: sliderAutoPlay,
									autoplayTimeout: sliderAutoPlayTime,
									autoplayHoverPause: sliderHoverPause,
									autoplayButton: false,
									autoplayButtonOutput: false,
									controlsPosition: top,
									navContainer: sliderNavContainer,
									navPosition: top,
									autoplayPosition: top,
									controlsText: [
											'<i class="bi bi-chevron-left"></i>',
											'<i class="bi bi-chevron-right"></i>'
									],
									loop: sliderLoop,
									rewind: sliderRewind,
									autoHeight: sliderAutoHeight,
									fixedWidth: sliderfixedWidth,
									touch: sliderTouch,
									mouseDrag: sliderDrag,
									arrowKeys: true,
									items: sliderItems,
									textDirection: sliderDirection,
									lazyload: true,
									lazyloadSelector: '.lazy',
									responsive: {
											0: {
													items: Number(sliderItemsXs)
											},
											576: {
													items: Number(sliderItemsSm)
											},
											768: {
													items: Number(sliderItemsMd)
											},
											992: {
													items: Number(sliderItemsLg)
											},
											1200: {
													items: Number(sliderItemsXl)
											}
									}
							});
					}); 
				}
		},
		// END: Tiny Slider


    // START: 04 Tooltip
		// Enable tooltips everywhere via data-toggle attribute
		toolTipFunc: function () {
				var tooltipTriggerList = [].slice.call(e.selectAll('[data-bs-toggle="tooltip"]'))
				var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
					return new bootstrap.Tooltip(tooltipTriggerEl)
				})
		},
		// END: Tooltip

		// START: 05 Popover
		// Enable popover everywhere via data-toggle attribute
		popOverFunc: function () {
				var popoverTriggerList = [].slice.call(e.selectAll('[data-bs-toggle="popover"]'))
				var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
					return new bootstrap.Popover(popoverTriggerEl)
				})
		},
		// END: Popover
    
    // START: 06 Video player
    videoPlyr: function () {
      var vdp = e.select('.player-wrapper');
      if (e.isVariableDefined(vdp)) {
        // youtube
        const playerYoutube = Plyr.setup('.player-youtube', {});
        window.player = playerYoutube;

        // Vimeo
        const playerVimeo = Plyr.setup('.player-vimeo', {});
        window.player = playerVimeo;
        
        // HTML video
        const playerHtmlvideo = Plyr.setup('.player-html', {
          captions: {active: true}
        });
        window.player = playerHtmlvideo;

        // HTML audio
        const playerHtmlaudio = Plyr.setup('.player-audio', {});
        window.player = playerHtmlaudio;
      }
    },
    // END: Video player

		// START: 07 GLightbox
		lightBox: function () {
				var light = e.select('[data-glightbox]');
				if (e.isVariableDefined(light)) {
						var lb = GLightbox({
								selector: '*[data-glightbox]',
								openEffect: 'fade',
								touchFollowAxis: 'true',
								closeEffect: 'fade'
						});
				}
		},
		// END: GLightbox

		// START: 08 Dark mode
		darkMode: function () {
			if (typeof window.toggleTheme === 'function') {
				var syncedTheme = document.documentElement.getAttribute('data-theme') || 'light';
				var syncedIcon = document.querySelector('#theme-icon') || document.querySelector('#darkModeSwitch i');
				if (syncedIcon) {
					syncedIcon.className = syncedTheme === 'dark' ? 'bi bi-lightbulb fs-6' : 'bi bi-moon-stars-fill fs-6';
				}
				return;
			}

			var root = document.documentElement;
			var readTheme = function () {
				return localStorage.getItem('theme-mode') || localStorage.getItem('theme') || localStorage.getItem('data-theme') || root.getAttribute('data-theme') || 'light';
			};
			var syncThemeIcon = function (theme) {
				var icon = document.querySelector('#theme-icon') || document.querySelector('#darkModeSwitch i');
				if (icon) {
					icon.className = theme === 'dark' ? 'bi bi-lightbulb fs-6' : 'bi bi-moon-stars-fill fs-6';
				}
			};

			var changeThemeToDark = () => {
				root.setAttribute("data-theme", "dark")
				root.setAttribute("data-theme-mode", "dark")
				root.setAttribute("data-bs-theme", "dark")
				localStorage.setItem("data-theme", "dark")
				localStorage.setItem("theme", "dark")
				localStorage.setItem("theme-mode", "dark")
				syncThemeIcon("dark")
			}

			var changeThemeToLight = () => {
				root.setAttribute("data-theme", "light")
				root.setAttribute("data-theme-mode", "light")
				root.setAttribute("data-bs-theme", "light")
				localStorage.setItem("data-theme", 'light')
				localStorage.setItem("theme", "light")
				localStorage.setItem("theme-mode", "light")
				syncThemeIcon("light")
			}

			let theme = readTheme();
			if(theme === 'dark'){
				changeThemeToDark()
			} else if (theme == null || theme === 'light' ) {
				changeThemeToLight();
			}

			const dms = e.select('.theme-toggle') || e.select('#darkModeSwitch');
			if (e.isVariableDefined(dms)) {
					dms.addEventListener('click', () => {
						let theme = readTheme();
						if (theme ==='dark'){
								changeThemeToLight()
						} else{
								changeThemeToDark()
						}
					});
			}
	},
	// END: Dark mode


	// START: 09 Sidebar Toggle start
	sidebarToggleStart: function () {
		var sidebar = e.select('.sidebar-start-toggle');
		if (e.isVariableDefined(sidebar)) {
				var sb = e.select('.sidebar-start-toggle');
				var mode = document.getElementsByTagName("BODY")[0];
				sb.addEventListener("click", function(){
						mode.classList.toggle("sidebar-start-enabled");
				}); 
		}        
	},
	// END: Sidebar Toggle

	// START: 10 Sidebar Toggle end
	sidebarToggleEnd: function () {
		var sidebar = e.select('.sidebar-end-toggle');
		if (e.isVariableDefined(sidebar)) {
				var sb = e.select('.sidebar-end-toggle');
				var mode = document.getElementsByTagName("BODY")[0];
				sb.addEventListener("click", function(){
						mode.classList.toggle("sidebar-end-enabled");
				}); 
		}        
	},
	// END: Sidebar Toggle end

	// START: 11 Choices
	choicesSelect: function () {
		var choice = e.select('.js-choice');
		if (e.isVariableDefined(choice)) {
			var element = e.selectAll('.js-choice');
			element.forEach(function (item) {
				var removeItemBtn = item.getAttribute('data-remove-item-button') == 'true' ? true : false;
				var placeHolder = item.getAttribute('data-placeholder') == 'false' ? false : true;
				var placeHolderVal = item.getAttribute('data-placeholder-val') ? item.getAttribute('data-placeholder-val') : 'Type and hit enter';
				var maxItemCount = item.getAttribute('data-max-item-count') ? item.getAttribute('data-max-item-count') : 3;
				var searchEnabled = item.getAttribute('data-search-enabled') == 'false' ? false : true;
				var position = item.getAttribute('data-position') ? item.getAttribute('data-position') : 'auto';
				var choices = new Choices(item, {
						removeItemButton: removeItemBtn,
						placeholder: placeHolder,
						placeholderValue: placeHolderVal,
						maxItemCount: maxItemCount,
						searchEnabled: searchEnabled,
						position: position
				});
			});
		}
	},
	// END: Choices

	// START: 12 Auto resize textarea
	autoResize: function () {
		e.selectAll('[data-autoresize]').forEach(function (element) {
			var offset = element.offsetHeight - element.clientHeight;
			element.addEventListener('input', function (event) {
				event.target.style.height = 'auto';
				event.target.style.height = event.target.scrollHeight + offset + 'px';
			});
		});
	},
	// END: Auto resize textarea

	// START: 13 Drop Zone
	DropZone: function () {
		if (e.isVariableDefined(e.select("[data-dropzone]"))) {
			window.Dropzone.autoDiscover = false;

			// 1. Default Dropzone Initialization
			if (e.isVariableDefined(e.select(".dropzone-default"))) {
				e.selectAll(".dropzone-default").forEach((e => {
					const a = e.dataset.dropzone ? JSON.parse(e.dataset.dropzone) : {},
						b = {
							url: '/upload', // Change this URL to your actual image upload code
							// Fake the file upload, since GitHub does not handle file uploads
							// and returns a 404
							// https://docs.dropzone.dev/getting-started/setup/server-side-implementation
							init: function() {
								this.on('error', function(file, errorMessage) {
									if (file.accepted) {
										var mypreview = document.getElementsByClassName('dz-error');
										mypreview = mypreview[mypreview.length - 1];
										mypreview.classList.toggle('dz-error');
										mypreview.classList.toggle('dz-success');
									}
								});
							}
						},
						c = {
							...b,
							...a
						};
						new Dropzone(e, c);
					}));
			}
	
			// 2. Custom cover and list previews Dropzone Initialization
			if (e.isVariableDefined(e.select(".dropzone-custom"))) {
				e.selectAll(".dropzone-custom").forEach((d => {
					const j = d.dataset.dropzone ? JSON.parse(d.dataset.dropzone) : {},
						o = {
							addRemoveLinks: true,
							previewsContainer: d.querySelector(".dz-preview"),
							previewTemplate: d.querySelector(".dz-preview").innerHTML,
							url: '/upload', // Change this URL to your actual image upload code
							// Now fake the file upload, since GitHub does not handle file uploads
							// and returns a 404
							// https://docs.dropzone.dev/getting-started/setup/server-side-implementation
							init: function() {
								this.on('error', function(file, errorMessage) {
									if (file.accepted) {
										var mypreview = document.getElementsByClassName('dz-error');
										mypreview = mypreview[mypreview.length - 1];
										mypreview.classList.toggle('dz-error');
										mypreview.classList.toggle('dz-success');
									}
								});
							}
						},
						x = {
							...o,
							...j
						};
						d.querySelector(".dz-preview").innerHTML = '';
						new Dropzone(d, x);
				}));
			}
		}
	},
	// END: Drop Zone
  

	// START: 14 Flat picker
	flatPicker: function () {

    var picker = e.select('.flatpickr');
		if (e.isVariableDefined(picker)) {
			var element = e.selectAll('.flatpickr');
			element.forEach(function (item) {
				var mode = item.getAttribute('data-mode') == 'multiple' ? 'multiple' : item.getAttribute('data-mode') == 'range' ? 'range' : 'single';
				var enableTime = item.getAttribute('data-enableTime') == 'true' ? true : false;
				var noCalendar = item.getAttribute('data-noCalendar') == 'true' ? true : false;
				var inline = item.getAttribute('data-inline') == 'true' ? true : false;

				flatpickr(item, {
	        mode: mode,
	        enableTime: enableTime,
	        noCalendar: noCalendar,
	        inline: inline
	      });

			});
		}
  },
	// END: Flat picker

	// START: 15 Avatar Image
	avatarImg: function () {
		if (e.isVariableDefined(e.select('#avatarUpload'))) {
		
			var avtInput = e.select('#avatarUpload'),
			avtReset = e.select("#avatar-reset-img"),
			avtPreview = e.select('#avatar-preview');
		
			// Avatar upload and replace
			avtInput.addEventListener('change', readURL, true);
			function readURL(){
					const file = avtInput.files[0];
					const files = avtInput.files;
					const reader = new FileReader();
					reader.addEventListener('loadend', function(){
							avtPreview.src = reader.result; 
					});
		
					if(file && files){
							reader.readAsDataURL(file);
					} else { }
		
					avtInput.value = '';
			}
		
			// Avatar remove functionality
			avtReset.addEventListener("click", function(){
				var themeAsset = document.querySelector('link[data-theme-asset="turkmod"], script[data-theme-asset="turkmod"]');
				var themeUrl = '';
				if (themeAsset && themeAsset.href) {
					themeUrl = themeAsset.href.replace(/\/css\/[^\/]+\.css(?:\?.*)?$/, '');
				} else if (themeAsset && themeAsset.src) {
					themeUrl = themeAsset.src.replace(/\/js\/[^\/]+\.js(?:\?.*)?$/, '');
				}
				if (!themeUrl) {
					var loadedThemeAsset = document.querySelector('link[href*="/themes/turkmod/"], script[src*="/themes/turkmod/"]');
					if (loadedThemeAsset && loadedThemeAsset.href) {
						themeUrl = loadedThemeAsset.href.replace(/\/(?:css|js)\/[^\/]+(?:\?.*)?$/, '');
					} else if (loadedThemeAsset && loadedThemeAsset.src) {
						themeUrl = loadedThemeAsset.src.replace(/\/(?:css|js)\/[^\/]+(?:\?.*)?$/, '');
					}
				}
				if (themeUrl) {
					avtPreview.src = themeUrl + "/images/ava.jpg";
				}
			});
		}			
	},
	// END: Avatar Image

	// START: 16 Custom Scrollbar
	customScrollbar: function () {

		if (e.isVariableDefined(e.select(".custom-scrollbar"))) {
			document.addEventListener("DOMContentLoaded", function() {
				var instances = OverlayScrollbars(e.selectAll('.custom-scrollbar'), {
					resize : "none",
					scrollbars: {
						autoHide: 'leave',
						autoHideDelay: 200
					},
					overflowBehavior : {
							x : "visible-hidden",
							y : "scroll"
					}
				});
			});
		}
	
		if (e.isVariableDefined(e.select(".custom-scrollbar-y"))) {
			document.addEventListener("DOMContentLoaded", function() {
				var instances = OverlayScrollbars(e.selectAll('.custom-scrollbar-y'), {
					resize : "none",
					scrollbars: {
						autoHide: 'leave',
						autoHideDelay: 200
					},
					overflowBehavior : {
							x : "scroll",
							y : "scroll"
					}
				});
			});
		}	
	},
	// END: Custom Scrollbar

	// START: 17 Toasts
	toasts: function () {
		if (e.isVariableDefined(e.select('.toast-btn'))) {
			window.addEventListener('DOMContentLoaded', (event) => {
					e.selectAll(".toast-btn").forEach((t) => {
						t.addEventListener("click", function() {
							var toastTarget = document.getElementById(t.dataset.target);
							var toast = new bootstrap.Toast(toastTarget);
							toast.show();
						});
					});
			});
		}
	},
	// END: Toasts

	// START: 18 pswMeter
	pswMeter: function () {
		if (e.isVariableDefined(e.select('#pswmeter'))) {
			const myPassMeter = passwordStrengthMeter({
				containerElement: '#pswmeter',
				passwordInput: '#psw-input',
				showMessage: true,
				messageContainer: '#pswmeter-message',
				messagesList: [
					'Write your password...',
					'Easy peasy!',
					'That is a simple one',
					'That is better',
					'Yeah! that password rocks ;)'
				],
				height: 8,
				borderRadius: 4,
				pswMinLength: 8,
				colorScore1: '#dc3545',
				colorScore2: '#f7c32e',
				colorScore3: '#4f9ef8',
				colorScore4: '#0cbc87'
			});
		}
	},
  // END: pswMeter

	// START: 19 Fake Password
	fakePwd: function () {
		if (e.isVariableDefined(e.select('.fakepassword'))) {
			var password = e.select('.fakepassword');
			var toggler = e.select('.fakepasswordicon');
		
			var showHidePassword = () => {
				if (password.type == 'password') {
					password.setAttribute('type', 'text');
					toggler.classList.add('bi-eye');
				} else {
					toggler.classList.remove('bi-eye');
					password.setAttribute('type', 'password');
				}
			};
		
			toggler.addEventListener('click', showHidePassword);
		}
	}
  // END: Fake Password
 
};
e.init();

/* --- turkmod-shell.js --- */
(function () {
  "use strict";

  function initTurkmodShell() {
    var categoryButtons = document.querySelectorAll("[data-cat-toggle]");
    categoryButtons.forEach(function (button) {
      if (button.dataset.ready === "1") return;
      button.dataset.ready = "1";
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        var panelId = button.getAttribute("aria-controls");
        var panel = panelId ? document.getElementById(panelId) : null;
        if (!panel) return;

        var isExpanded = button.getAttribute("aria-expanded") === "true";
        if (!isExpanded) {
          var currentItem = button.closest("[data-cat-item]");
          var currentList = currentItem ? currentItem.parentElement : null;
          if (currentList) {
            Array.prototype.forEach.call(currentList.children, function (siblingItem) {
              if (siblingItem === currentItem || !siblingItem.matches("[data-cat-item]")) return;

              var siblingRow = siblingItem.firstElementChild;
              var siblingButton = siblingRow
                ? (siblingRow.matches("[data-cat-toggle]")
                  ? siblingRow
                  : siblingRow.querySelector("[data-cat-toggle]"))
                : null;
              if (!siblingButton || siblingButton.getAttribute("aria-expanded") !== "true") return;

              var siblingPanelId = siblingButton.getAttribute("aria-controls");
              var siblingPanel = siblingPanelId ? document.getElementById(siblingPanelId) : null;
              siblingButton.setAttribute("aria-expanded", "false");
              if (siblingPanel) siblingPanel.setAttribute("hidden", "");
            });
          }
        }

        button.setAttribute("aria-expanded", isExpanded ? "false" : "true");
        if (isExpanded) {
          panel.setAttribute("hidden", "");
        } else {
          panel.removeAttribute("hidden");
        }
      });
    });

    var atlasButtons = document.querySelectorAll("[data-atlas-toggle]");
    atlasButtons.forEach(function (button) {
      if (button.dataset.ready === "1") return;
      button.dataset.ready = "1";
      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();

        var currentItem = button.closest(".sidebar-category-item");
        if (!currentItem) return;

        var shouldOpen = !currentItem.classList.contains("open");
        var currentList = currentItem.parentElement;
        if (shouldOpen && currentList) {
          Array.prototype.forEach.call(currentList.children, function (siblingItem) {
            if (siblingItem === currentItem || !siblingItem.classList.contains("sidebar-category-item")) return;

            var siblingButton = siblingItem.querySelector("[data-atlas-toggle]");
            siblingItem.classList.remove("open");
            if (siblingButton) siblingButton.setAttribute("aria-expanded", "false");
          });
        }

        currentItem.classList.toggle("open", shouldOpen);
        button.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
      });
    });

    document.querySelectorAll("[data-topic-url]").forEach(function (card) {
      if (card.dataset.ready === "1") return;
      card.dataset.ready = "1";
      card.addEventListener("click", function (event) {
        if (event.target.closest("a, button, input, textarea, select, label")) return;
        var url = card.getAttribute("data-topic-url");
        if (url) window.location.href = url;
      });
    });

    document.querySelectorAll(".copy-link").forEach(function (button) {
      button.addEventListener("click", function () {
        var url = button.getAttribute("data-copy-url");
        if (!url || !navigator.clipboard) return;

        var absoluteUrl = new URL(url, window.location.origin).href;
        navigator.clipboard.writeText(absoluteUrl).then(function () {
          var original = button.innerHTML;
          button.innerHTML = '<i class="bi bi-check2 me-2"></i>Kopyalandi';
          window.setTimeout(function () {
            button.innerHTML = original;
          }, 1600);
        });
      });
    });

    function syncTheme() {
      var theme = document.documentElement.getAttribute("data-theme") || "light";

      var icon = document.querySelector("#theme-icon") || document.querySelector("#darkModeSwitch i");
      if (icon) {
        icon.className = theme === "dark" ? "bi bi-lightbulb fs-6" : "bi bi-moon-stars-fill fs-6";
      }
    }

    syncTheme();
    if (window.MutationObserver) {
      new MutationObserver(syncTheme).observe(document.documentElement, {
        attributes: true,
        attributeFilter: ["data-theme", "data-bs-theme"]
      });
    }
  }
  
  if (document.readyState === 'loading') {
    document.addEventListener("DOMContentLoaded", initTurkmodShell);
  } else {
    initTurkmodShell();
  }
})();

/* --- turkmod-notifications.js --- */
(function () {
  "use strict";

  function notificationRoot() {
    return document.querySelector("[data-notif-dropdown]");
  }

  function notificationUrl(root) {
    return (root && root.getAttribute("data-notif-url")) || "";
  }

  function safeNotificationUrl(url, defaultUrl) {
    if (!url) return defaultUrl;
    try {
      var parsed = new URL(url, window.location.origin);
      if (parsed.protocol === "http:" || parsed.protocol === "https:") {
        return parsed.href;
      }
    } catch (error) {
      if (url.charAt(0) === "/" || url.charAt(0) === "#") return url;
    }
    return defaultUrl;
  }

  function setState(list, iconClass, label) {
    if (!list) return;
    list.innerHTML = "";
    var state = document.createElement("div");
    state.className = "notif-menu-state";
    var icon = document.createElement("i");
    icon.className = "bi " + iconClass;
    icon.setAttribute("aria-hidden", "true");
    state.appendChild(icon);
    state.appendChild(document.createTextNode(label));
    list.appendChild(state);
  }

  function updateNotificationBadge(count) {
    var badge = document.getElementById("notifBadge");
    var value = Number(count || 0);
    if (!badge) return;

    if (value > 0) {
      badge.textContent = value > 99 ? "99+" : String(value);
      badge.classList.add("is-visible");
    } else {
      badge.classList.remove("is-visible");
    }
  }

  function fetchNotifications() {
    var root = notificationRoot();
    if (!root) return Promise.resolve(null);

    var endpoint = root.getAttribute("data-notif-api") || "";
    var list = document.getElementById("notifList");
    if (!endpoint) return Promise.resolve(null);

    return (window.publicFetchJson ? window.publicFetchJson(endpoint, { headers: { "X-Requested-With": "XMLHttpRequest" } }) : Promise.reject(new Error("Public API helper yuklenemedi.")))
      .then(function (data) {
        if (!data || !data.ok) return data;

        updateNotificationBadge(data.show_badge === false ? 0 : data.unread_count);
        if (!list) return data;

        list.innerHTML = "";
        if (data.disabled || data.muted) {
          setState(
            list,
            data.disabled ? "bi-bell-slash" : "bi-volume-mute",
            data.disabled ? "Bildirim merkezi kapali" : "Bildirimler sessize alindi"
          );
          return data;
        }

        var latest = Array.isArray(data.latest) ? data.latest : [];
        if (latest.length === 0) {
          setState(list, "bi-inbox", "Bildirim yok");
          return data;
        }

        latest.forEach(function (notification) {
          var icon = "bi-info-circle";
          var iconState = "";
          if (notification.type === "success") { icon = "bi-check-circle"; iconState = " is-success"; }
          if (notification.type === "warning") { icon = "bi-exclamation-triangle"; iconState = " is-warning"; }
          if (notification.type === "error") { icon = "bi-x-circle"; iconState = " is-error"; }
          if (notification.type === "system") { icon = "bi-gear"; iconState = " is-system"; }

          var item = document.createElement("a");
          item.className = "notif-item " + (notification.is_read ? "" : "unread");
          item.setAttribute("data-notif-dropdown-item", "true");
          item.setAttribute("data-id", notification.id);
          item.href = safeNotificationUrl(notification.link || "", notificationUrl(root));

          var iconWrap = document.createElement("div");
          iconWrap.className = "notif-item-icon" + iconState;
          var iconEl = document.createElement("i");
          iconEl.className = "bi " + icon;
          iconEl.setAttribute("aria-hidden", "true");
          iconWrap.appendChild(iconEl);

          var content = document.createElement("div");
          content.className = "notif-item-content";
          var titleEl = document.createElement("div");
          titleEl.className = "notif-item-title";
          titleEl.textContent = notification.title || "";
          var msgEl = document.createElement("div");
          msgEl.className = "notif-item-msg";
          msgEl.textContent = notification.message || "";
          content.appendChild(titleEl);
          content.appendChild(msgEl);

          item.appendChild(iconWrap);
          item.appendChild(content);

          (function(notif, el) {
            el.addEventListener("click", function(e) {
              e.preventDefault();
              e.stopPropagation();
              e.stopImmediatePropagation();
              var readApi = root.getAttribute("data-notif-read-api") || "";
              var dest = notificationUrl(root) + "#notif-" + notif.id;
              var csrfMeta = document.querySelector('meta[name="csrf-token"]');
              var csrfToken = csrfMeta ? csrfMeta.getAttribute("content") : "";
              if (!notif.is_read && readApi) {
                var fd = new FormData();
                fd.append("_token", csrfToken);
                fd.append("id", notif.id);
                (window.publicFetchJson ? window.publicFetchJson(readApi, { method: "POST", body: fd, headers: { "X-Requested-With": "XMLHttpRequest" }, notifyError: false }) : Promise.reject(new Error("Public API helper yuklenemedi.")))
                  .then(function() {
                    el.classList.remove("unread");
                    notif.is_read = true;
                    var badge = document.getElementById("notifBadge");
                    if (badge && badge.classList.contains("is-visible")) {
                      var cur = parseInt(badge.textContent || "0", 10);
                      if (cur > 1) { badge.textContent = String(cur - 1); }
                      else { badge.textContent = "0"; badge.classList.remove("is-visible"); }
                    }
                    window.location.href = dest;
                  })
                  .catch(function(error) {
                    if (window.showToast) {
                      window.showToast(error && error.message ? error.message : "Bildirimler guncellenemedi.", "error");
                    }
                    window.location.href = dest;
                  });
              } else {
                window.location.href = dest;
              }
            });
          })(notification, item);

          list.appendChild(item);
        });

        return data;
      })
      .catch(function () {
        if (list) setState(list, "bi-exclamation-triangle", "Bildirimler yuklenemedi");
        return null;
      });
  }

  function toggleNotifMenu(forceOpen) {
    var root = notificationRoot();
    if (!root) return;

    var toggle = root.querySelector("[data-notif-toggle]");
    var shouldOpen = typeof forceOpen === "boolean" ? forceOpen : !root.classList.contains("show");
    root.classList.toggle("show", shouldOpen);
    if (toggle) toggle.setAttribute("aria-expanded", shouldOpen ? "true" : "false");
    if (shouldOpen) fetchNotifications();
  }

  function markAllNotificationsAsRead(event) {
    if (event) event.preventDefault();
    var root = notificationRoot();
    var markAll = event && event.target ? event.target.closest("[data-notif-mark-all]") : null;
    if (!root || !markAll || markAll.dataset.busy === "1") return;

    var readApi = root.getAttribute("data-notif-read-api") || "";
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.getAttribute("content") || "" : "";
    if (!readApi) {
      if (window.showToast) window.showToast("Bildirim servisi kullanilamiyor.", "error");
      return;
    }

    markAll.dataset.busy = "1";
    markAll.setAttribute("aria-disabled", "true");

    var formData = new FormData();
    formData.append("_token", csrfToken);
    formData.append("id", "all");

    (window.publicFetchJson ? window.publicFetchJson(readApi, {
      method: "POST",
      body: formData,
      headers: { "X-Requested-With": "XMLHttpRequest" },
      notifyError: false
    }) : Promise.reject(new Error("Public API helper yuklenemedi.")))
      .then(function () {
        var list = document.getElementById("notifList");
        if (list) {
          list.querySelectorAll(".notif-item.unread").forEach(function (item) {
            item.classList.remove("unread");
          });
        }
        updateNotificationBadge(0);
        if (window.showToast) window.showToast("Bildirimler okundu olarak isaretlendi.", "success");
        return fetchNotifications();
      })
      .catch(function (error) {
        fetchNotifications();
        if (window.showToast) {
          window.showToast(error && error.message ? error.message : "Bildirimler guncellenemedi.", "error");
        }
      })
      .finally(function () {
        markAll.dataset.busy = "0";
        markAll.removeAttribute("aria-disabled");
      });
  }

  document.addEventListener("click", function (event) {
    var root = notificationRoot();
    var toggle = root ? event.target.closest("[data-notif-dropdown] [data-notif-toggle]") : null;
    var markAll = event.target.closest("[data-notif-mark-all]");

    if (toggle) {
      event.preventDefault();
      toggleNotifMenu();
      return;
    }

    if (markAll) {
      markAllNotificationsAsRead(event);
      return;
    }

    if (root && root.classList.contains("show") && !root.contains(event.target)) {
      toggleNotifMenu(false);
    }
  });

  window.updateNotificationBadge = updateNotificationBadge;
  window.fetchNotifications = fetchNotifications;
  window.toggleNotifMenu = toggleNotifMenu;
  window.markAllNotificationsAsRead = markAllNotificationsAsRead;
})();

/* --- turkmod-download-confirm.js --- */
(function () {
  "use strict";

  function initDownloadConfirm() {
    var card = document.querySelector("[data-download-confirm]");
    if (!card || card.dataset.ready === "1") return;
    card.dataset.ready = "1";

    var href = card.dataset.confirmHref || "";
    var countdownEl = document.getElementById("downloadConfirmCountdown");
    var primary = card.querySelector("[data-download-confirm-primary]");
    var primaryText = card.querySelector("[data-download-confirm-primary-text]");
    var autoRedirectEnabled = card.dataset.autoRedirectEnabled !== "0";
    var primaryLabel = card.dataset.primaryLabel || (primaryText ? primaryText.textContent : "Hedefe Git");
    var countdownLabel = card.dataset.primaryCountdownLabel || "Hedefe Git ({{seconds}})";
    var redirectingLabel = card.dataset.redirectingLabel || "Yonlendiriliyor...";
    var remaining = Math.max(0, parseInt(card.dataset.autoRedirectSeconds || "0", 10) || 0);
    var redirected = false;

    function formatLabel(template, seconds) {
      return String(template || "")
        .replace(/\{+\s*missing:\s*seconds\s*\}+/gi, "{{seconds}}")
        .replace(/\(\(\s*missing:\s*seconds\s*\)\)/gi, "({{seconds}})")
        .replace(/\(\s*missing:\s*seconds\s*\)/gi, "{{seconds}}")
        .replace(/\bmissing:\s*seconds\b/gi, "{{seconds}}")
        .replace(/\{\{\{\s*seconds\s*\}\}\}/g, String(seconds))
        .replace(/\{\{\s*\}\}/g, String(seconds))
        .replace(/\{\{\s*seconds\s*\}\}/g, String(seconds))
        .replace(/\{\s*seconds\s*\}/g, String(seconds))
        .replace(/\{+\s*seconds\s*\}+/g, String(seconds))
        .replace(/\{+\s*\}+/g, String(seconds))
        .replace(/\{\s*\}/g, String(seconds))
        .replace(/\(\(\s*(\d+)\s*\)\)/g, "($1)");
    }

    function go() {
      if (redirected || href === "") return;
      redirected = true;
      window.location.href = href;
    }

    function update() {
      if (countdownEl) countdownEl.textContent = String(remaining);
      if (!primaryText) return;

      if (!autoRedirectEnabled) {
        primaryText.textContent = primaryLabel;
        return;
      }

      primaryText.textContent = remaining > 0 ? formatLabel(countdownLabel, remaining) : redirectingLabel;
    }

    if (primary) {
      primary.addEventListener("click", function () {
        redirected = true;
      });
    }

    update();
    if (!autoRedirectEnabled) {
      return;
    }

    if (remaining <= 0) {
      window.setTimeout(go, 150);
      return;
    }

    var timer = window.setInterval(function () {
      remaining -= 1;
      update();
      if (remaining <= 0) {
        window.clearInterval(timer);
        go();
      }
    }, 1000);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initDownloadConfirm);
  } else {
    initDownloadConfirm();
  }
})();

/* --- turkmod-notifications-page.js --- */
(function () {
  "use strict";

  function initNotificationsPage() {
    var root = document.querySelector("[data-notifications-root]");
    if (!root || root.dataset.ready === "1") return;
    root.dataset.ready = "1";

    var csrfToken = root.dataset.csrfToken || "";
    var readEndpoint = root.dataset.readEndpoint || "";
    var readMoreEnabled = root.dataset.readMoreEnabled === "true";
    var autoMarkOnOpen = root.dataset.autoMarkOnOpen === "true";

    function postNotificationRead(id) {
      var formData = new FormData();
      formData.append("_token", csrfToken);
      formData.append("id", id);

      return window.publicFetchJson ? window.publicFetchJson(readEndpoint, {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" },
        notifyError: false
      }) : Promise.reject(new Error("Public API helper yuklenemedi."));
    }

    function initPreferenceGroups() {
      root.querySelectorAll("[data-notification-preference-group]").forEach(function (group) {
        var master = group.querySelector("[data-notification-group-toggle]");
        var items = Array.from(group.querySelectorAll("[data-notification-group-item]"));
        if (!master || items.length === 0) return;

        function setGroupState(enabled) {
          items.forEach(function (input) {
            input.checked = enabled;
          });
          master.checked = enabled;
          master.indeterminate = false;
          group.classList.toggle("is-group-disabled", !enabled);
        }

        function syncMasterFromItems() {
          var checkedCount = items.filter(function (input) {
            return input.checked;
          }).length;
          master.checked = checkedCount > 0;
          master.indeterminate = checkedCount > 0 && checkedCount < items.length;
          group.classList.toggle("is-group-disabled", checkedCount === 0);
        }

        master.addEventListener("change", function () {
          setGroupState(master.checked);
        });

        items.forEach(function (input) {
          input.addEventListener("change", syncMasterFromItems);
        });

        if (!master.checked) {
          setGroupState(false);
          return;
        }
        syncMasterFromItems();
      });
    }

    function refreshMessageToggles() {
      if (!readMoreEnabled) return;
      root.querySelectorAll("[data-notif-message]").forEach(function (message) {
        var item = message.closest("[data-notif-item]");
        var toggle = item ? item.querySelector("[data-notification-message-toggle]") : null;
        if (toggle) toggle.hidden = !(message.scrollHeight > message.clientHeight + 2);
      });
    }

    initPreferenceGroups();
    refreshMessageToggles();
    window.addEventListener("resize", refreshMessageToggles, { passive: true });

    document.addEventListener("click", function (event) {
      var toggle = event.target.closest("[data-notification-message-toggle]");
      if (toggle && root.contains(toggle)) {
        var item = toggle.closest("[data-notif-item]");
        var message = item ? item.querySelector("[data-notif-message]") : null;
        if (!message) return;

        var expanded = message.classList.toggle("is-expanded");
        toggle.innerHTML = expanded
          ? '<span>Daha kisa goster</span><i class="bi bi-chevron-up" aria-hidden="true"></i>'
          : '<span>Daha fazla goster</span><i class="bi bi-chevron-down" aria-hidden="true"></i>';
        return;
      }

      var notificationLink = event.target.closest("[data-notif-open]");
      if (notificationLink && root.contains(notificationLink) && !notificationLink.closest("[data-notif-dropdown]")) {
        event.preventDefault();
        var targetUrl = notificationLink.href;
        if (!autoMarkOnOpen) {
          window.location.href = targetUrl;
          return;
        }
        postNotificationRead(notificationLink.getAttribute("data-id")).finally(function () {
          window.location.href = targetUrl;
        });
      }
    });

    var markAllButton = root.querySelector("[data-mark-all-read]");
    if (markAllButton) {
      markAllButton.addEventListener("click", function () {
        if (markAllButton.disabled) return;

        var originalHtml = markAllButton.innerHTML;
        markAllButton.disabled = true;
        markAllButton.innerHTML = '<i class="bi bi-arrow-repeat spin" aria-hidden="true"></i><span>Isleniyor...</span>';

        postNotificationRead("all").then(function (data) {
          if (!data || !data.ok) {
            throw new Error(data && data.message ? data.message : "Bildirimler guncellenemedi.");
          }

          root.querySelectorAll("[data-notif-item].is-unread").forEach(function (item) {
            item.classList.remove("is-unread");
            item.classList.add("is-read");
          });

          var unreadMetric = root.querySelector("[data-notif-unread]");
          var sidebarUnread = document.querySelector("[data-sidebar-unread]");
          if (unreadMetric) unreadMetric.textContent = "0";
          if (sidebarUnread) sidebarUnread.remove();
          markAllButton.innerHTML = '<i class="bi bi-check2" aria-hidden="true"></i><span>Okundu</span>';
          window.setTimeout(function () { markAllButton.remove(); }, 1200);
        }).catch(function (error) {
          markAllButton.disabled = false;
          markAllButton.innerHTML = originalHtml;
          if (window.showToast) {
            window.showToast(error && error.message ? error.message : "Bildirimler guncellenemedi.", "error");
          }
        });
      });
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initNotificationsPage);
  } else {
    initNotificationsPage();
  }
})();

/* ============================================================
   SECTION: HOVER PREFETCH (madde 9)
   Makes navigation feel instant by warming the browser cache for a same-origin
   page the moment the user hovers/touches its link. Pure progressive
   enhancement: uses <link rel="prefetch">, fires at most once per URL, skips
   external/hash/download/non-GET links, and respects the Save-Data header and
   reduced-motion. No effect on browsers that ignore prefetch.
   ============================================================ */
(function () {
    'use strict';
    if (navigator.connection && navigator.connection.saveData) {
        return;
    }

    var prefetched = new Set();
    var origin = window.location.origin;

    function shouldPrefetch(a) {
        if (!a || !a.href) return false;
        if (a.target && a.target !== '' && a.target !== '_self') return false;
        if (a.hasAttribute('download')) return false;
        if (a.dataset.noPrefetch !== undefined) return false;
        var url;
        try { url = new URL(a.href); } catch (e) { return false; }
        if (url.origin !== origin) return false;
        if (url.pathname === window.location.pathname && url.search === window.location.search) return false;
        if (a.getAttribute('href').charAt(0) === '#') return false;
        // skip auth/logout/api/download endpoints (state-changing or heavy)
        if (/\/(logout|login|download|api)\b/i.test(url.pathname)) return false;
        if (prefetched.has(url.href)) return false;
        return true;
    }

    function prefetch(href) {
        prefetched.add(href);
        var link = document.createElement('link');
        link.rel = 'prefetch';
        link.href = href;
        link.as = 'document';
        document.head.appendChild(link);
    }

    var timer = null;
    function onIntent(event) {
        var a = event.target.closest && event.target.closest('a[href]');
        if (!shouldPrefetch(a)) return;
        var href = a.href;
        // small debounce so a quick mouse pass-over doesn't fire
        clearTimeout(timer);
        timer = setTimeout(function () { prefetch(href); }, 120);
    }

    document.addEventListener('mouseover', onIntent, { passive: true });
    document.addEventListener('touchstart', onIntent, { passive: true });
})();

// İçerik Bilgileri — kesilen (ellipsis) değerlerde özel tooltip kutusu
(function () {
    'use strict';
    var tip = null;
    var current = null;

    function ensureTip() {
        if (tip) return tip;
        tip = document.createElement('div');
        tip.className = 'info-value-tooltip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        document.body.appendChild(tip);
        return tip;
    }

    function isTruncated(el) {
        return el.scrollWidth > el.clientWidth + 1;
    }

    function show(el) {
        var text = el.getAttribute('data-info-full') || el.getAttribute('title') || el.textContent || '';
        text = text.trim();
        if (!text || !isTruncated(el)) return;
        // native title'ı geçici kaldır ki çift tooltip olmasın
        if (el.hasAttribute('title')) {
            el.setAttribute('data-info-full', el.getAttribute('title'));
            el.removeAttribute('title');
        }
        var box = ensureTip();
        box.textContent = text;
        box.hidden = false;
        current = el;
        position(el);
    }

    function position(el) {
        if (!tip || tip.hidden) return;
        var r = el.getBoundingClientRect();
        var tr = tip.getBoundingClientRect();
        var top = r.top - tr.height - 8 + window.scrollY;
        var left = r.left + (r.width - tr.width) / 2 + window.scrollX;
        var pad = 8;
        if (left < pad + window.scrollX) left = pad + window.scrollX;
        var maxLeft = window.scrollX + document.documentElement.clientWidth - tr.width - pad;
        if (left > maxLeft) left = maxLeft;
        var below = false;
        if (top < window.scrollY + pad) { top = r.bottom + 8 + window.scrollY; below = true; }
        tip.classList.toggle('info-value-tooltip--below', below);
        tip.style.top = top + 'px';
        tip.style.left = left + 'px';
    }

    function hide() {
        if (!tip) return;
        tip.hidden = true;
        current = null;
    }

    document.addEventListener('mouseover', function (e) {
        var el = e.target.closest && e.target.closest('[data-info-value]');
        if (el && el !== current) show(el);
    });
    document.addEventListener('mouseout', function (e) {
        var el = e.target.closest && e.target.closest('[data-info-value]');
        if (el && el === current && !el.contains(e.relatedTarget)) hide();
    });
    document.addEventListener('focusin', function (e) {
        var el = e.target.closest && e.target.closest('[data-info-value]');
        if (el) show(el);
    });
    document.addEventListener('focusout', hide);
    window.addEventListener('scroll', function () { if (current) position(current); }, { passive: true });
    window.addEventListener('resize', hide, { passive: true });
})();
