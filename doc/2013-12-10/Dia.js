// encoding="UTF-8"


/**
 * Simple javascript slideshow
 */


function addEvent(obj, evType, fn){ 
 if (obj.addEventListener){ 
   obj.addEventListener(evType, fn, false); 
   return true; 
 } else if (obj.attachEvent){ 
   var r = obj.attachEvent("on"+evType, fn); 
   return r; 
 } else { 
   return false; 
 } 
}

var Dia = {
  start:"",
  end:"",
  init:function (tagName, className) {
    // déjà fait ?
    if (Dia.start) return;
    var tdm='<form name="tdm"><select id="tdmSelect">';
    // ou bien
    // if (arguments.callee.done) return; else arguments.callee.done = true;
    if (!tagName) tagName="TD";
    if (!className) className="dia";
    var list = document.getElementsByTagName(tagName);
    var max=list.length;
    var prev;
    var o;
    for (var i=0;i<max;i++) {
      o=list[i];
      if (o.className != className) continue;
      if (!o.id) o.id="dia"+i;
      if (!Dia.start) Dia.start=o.id;
      if(prev) {
        prev.next=o.id;
        o.prev=prev.id;
      }
      // chercher un titre
      if (!o.title) {
        var h1=o.getElementsByTagName("H1");
        if (h1.length) o.title=(h1[0].innerText||h1[0].textContent);
        else o.title=(o.innerText||o.textContent).substring(0, 60)+"…";
      }
      // table des matières
      tdm += '<option value="'+o.id+'">'+o.title+'</option>';
      // keep last one
      Dia.end=o.id;
      prev=o;
    }
    tdm += "</select></form>";
    var nav=document.getElementById("nav");
    if (!nav) return;
    nav.innerHTML=tdm;
    select=document.getElementById('tdmSelect');
    select.onchange=Dia.go;
  },
  /**
   * Dimensionne la hauteur d'une diapo 
   * IE demande à ce que body soit chargé pour en trouver la hauteur
   */
  size:function() {
    Dia.init();
    document.body.style.fontSize="3ex";
    var width=window.innerWidth;
    if (!width && document.body) width=document.body.clientWidth;
    height=window.innerHeight;
    if (!height && document.body) height=document.body.clientHeight;
    if (!height) return;
    Dia.css('td.dia' , 'height:'+height+'px;');
  },
  css: function(selector, rule) {
    if ( document.styleSheets && document.styleSheets.length > 0) {
      var last_style_node = document.styleSheets[document.styleSheets.length - 1];
      // pas pour Gecko
      if (typeof(last_style_node.addRule) == "object") { 
        last_style_node.addRule(selector, rule);
        return;
      }
    }
    // créer un noeud <style type="text/css" media="screen">selector {rule}</style>
    var node = document.createElement("style");
    node.setAttribute("type", "text/css");
    node.setAttribute("media", "screen"); 
    // IE ? peut rien faire ici
    if(node.canHaveHTML == false) return;
    node.appendChild(document.createTextNode(selector + " {" + rule + "}"));
    document.getElementsByTagName("head")[0].appendChild(node);

  },
  go: function (prev) {
    var hash=window.location.hash.substr(1);
    if(!hash) hash=Dia.start;
    var from=document.getElementById(hash);
    if (!from) return "#";
    var to;
    // select depuis la tdm
    if (this.selectedIndex) to=this.options[this.selectedIndex].value;
    else if (prev) to=from.prev;
    else to=from.next;
    if(!to) return "#";
    window.location.hash="#"+to;
    select=document.getElementById('tdmSelect');
    if (select) {
      for (var i=select.options.length - 1; i > -1 ; i--) {
        option=select.options[i];
        if (option.value == to) option.selected=true;
        else option.selected=null;
      }
    }
    return "#"+to;
  },
  next:function () {
    return Dia.go();
  },
  prev:function () {
    return Dia.go(true);
  },
  first:function () {
    Dia.init();
    return "#"+Dia.start;
  },
  end:function () {
    Dia.init();
    return "#"+Dia.end;
  }
}
window.onresize=Dia.size;
// fixer la taille au chargement du head ne marche dans IE (pas encore de body)
window.onload=Dia.size;
