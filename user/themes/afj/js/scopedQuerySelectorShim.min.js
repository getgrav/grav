/* scopeQuerySelectorShim.js
*
* Copyright (C) 2015 Larry Davis
* All rights reserved.
*
* This software may be modified and distributed under the terms
* of the BSD license.  See the LICENSE file for details.
*/
!function(){function a(a,c){var e=a[c];a[c]=function(a){var c,f=!1,g=!1;return a.match(d)?(a=a.replace(d,""),this.parentNode||(b.appendChild(this),g=!0),parentNode=this.parentNode,this.id||(this.id="rootedQuerySelector_id_"+(new Date).getTime(),f=!0),c=e.call(parentNode,"#"+this.id+" "+a),f&&(this.id=""),g&&b.removeChild(this),c):e.call(this,a)}}if(!HTMLElement.prototype.querySelectorAll)throw new Error("rootedQuerySelectorAll: This polyfill can only be used with browsers that support querySelectorAll");var b=document.createElement("div");try{b.querySelectorAll(":scope *")}catch(c){var d=/^\s*:scope/gi;a(HTMLElement.prototype,"querySelector"),a(HTMLElement.prototype,"querySelectorAll")}}();