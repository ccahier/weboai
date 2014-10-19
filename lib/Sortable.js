/**
 * © 2009, 2012 frederic.glorieux@algone.net
 *
 * This program is a free software: you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License 
 * http://www.gnu.org/licenses/lgpl.html
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 */
/**
<h1>Sorting tables, short and fast.</h1>

Examples
<ul>
  <li><a href="http://developpements.enc.sorbonne.fr/diple/modules/xmlstats/?corpus=Littr%C3%A9,%20Nuances&taglist=">http://developpements.enc.sorbonne.fr/diple/modules/xmlstats/</a></li>
</ul>

<pre>
<table class="Sortable">
<!-- ... -->
</table>
<!-- Will make sortable all tables with the sortable className -->
<script src="../diple/js/Sortable.js">//</script>

</pre>

<p>
  On a today (2011) laptop, less than 1s. on 10 000 rows.
</p>

<b>onLoad</b>
<ul>
  <li>Create a String Array image of a table.</li>
  <li>For each row, keep the html as a String object in the Array.</li>
  <li>For each cell, store a sort key as an Attribute of the String image of the row.</li>
  <li>Add onClick events on top cells</li>
</ul>
<b>onClick</b>
<ul>
  <li>Modify the String.prototype.toString to return the key of the requested column.</li>
  <li>Sort the String array, image of the table, with the regular javascript sort() method. Sorting will be done on what will return the toString() method, modified upper, so that sort will be donne on a column sort key, and not the html of a row.</li>
  <li>(restore String.prototype.toString)</li>
  <li>The array will now have all the html row in the right order, affect it as html for the table, tbody.innerHTML=tbody.lines.join("\n");</li>
</ul>

Credit :

<ul>
  <li><a href="http://blog.vjeux.com/2009/javascript/speed-up-javascript-sort.html">http://blog.vjeux.com/2009/javascript/speed-up-javascript-sort.html</a></li>
  <li><a href="http://www.joostdevalk.nl/code/sortable-table/">http://www.joostdevalk.nl/code/sortable-table/</a></li>
  <li><a href="http://www.kryogenix.org/code/browser/sorttable/">http://www.kryogenix.org/code/browser/sorttable/</a></li>
</ul>
*/


var Sortable = {
  /** last key */
  lastKey : 'key0',
  /**
   * call on the load document event, loop on table to put sort arrows
   */
  load: function() {
    // Find all tables with class sortable and make them sortable
    if (!document.getElementsByTagName) return;
    tables = document.getElementsByTagName("table");
    for (var i=tables.length - 1; i >= 0; i--) {
      table = tables[i];
      if (((' ' + table.className.toLowerCase() + ' ').indexOf("sortable") != -1)) {
        Sortable.create(table);
      }
    }
  },
  /**
   * Sort a prepared table. For string values, the trick is to modify the String.toString() method
   * so that we can give what we want for table row <tr>, especially a prepared sort key
   * for the requested row. row[key] is a 10 chars string prepared with this.key().
   */
  sort: function(table, key, reverse) {

    var tbody;
    for (var i = 0; i < table.tBodies.length; i++) {


      tbody=table.tBodies[i];

      // waited object not found, go out
      if (!tbody.lines) continue;
      // numerical key
      if (tbody.lines[0][key] === +tbody.lines[0][key]) {
        var comparator=function(a, b) {
          return a[key] - b[key];
        }
        if (reverse) tbody.lines.reverse(comparator);
        else tbody.lines.sort(comparator);
      }
      else {
        // save native String.toString()
        var save = String.prototype.toString;
        // special case, a second sort, return 2 keys to sort on 2 columns
        if (Sortable.lastKey && Sortable.lastKey != key) {
          // if last key is numerical, normalize it with some 0000
          if (tbody.lines[0][Sortable.lastKey] === +tbody.lines[0][Sortable.lastKey]) {
            String.prototype.toString = function () { return this[key]+"0000000000".substr((''+this[Sortable.lastKey]).length)+this[Sortable.lastKey];};
          }
          else {
            String.prototype.toString = function () { return this[key]+this[Sortable.lastKey];};
          }
        }
        // set the method
        else String.prototype.toString = function () { return this[key];};
        // do the sort
        if (reverse) tbody.lines.reverse();
        else tbody.lines.sort();
        // restore native String.toString()
        String.prototype.toString = save;
      }
      // affect the sorted <tr> to the table as a innerHTML
      tbody.innerHTML=tbody.lines.join("\n");
    }
    // zebra the table after sort
    Sortable.zebra(table);
  },
  /**
   * normalize table, add events to top cells, take a copy of the table as an array of string
   *
   */
  create: function(table) {
    if (table.sortable) return false;
    // not enough rows, go away
    if (table.rows.length < 2) return false;
    // if no tHead, create it with first row
    if (!table.tHead) {
      table.createTHead().appendChild(table.rows[0]);
    }
    // loop on tbodies
    var row, s;
    for (var i = 0; i < table.tBodies.length; i++) {
      // create the array of lines
      table.tBodies[i].lines=new Array();
      for (j=table.tBodies[i].rows.length-1; j >=0; j--) {
        row=table.tBodies[i].rows[j];
        Sortable.paint(row, j);
        // get the <tr> html as a String object
        s=new String(row.outerHTML || new XMLSerializer().serializeToString(row).replace(' xmlns="http://www.w3.org/1999/xhtml"', ''));
        for (k=row.cells.length -1; k>-1; k--) s['key'+k]=Sortable.key(row.cells[k]);
        table.tBodies[i].lines[j]=s;
      }
    }
    firstRow = table.tHead.rows[0]; // table.tHead.rows.length-1 // last ?
    // We have a first row: assume it's the header, and make its contents clickable links
    var cell;
    for (var i=0;i<firstRow.cells.length;i++) {
      cell = firstRow.cells[i];
      var text = cell.innerHTML.replace(/<.+>/g, '');
      if (cell.className.indexOf("unsort") != -1 || cell.className.indexOf("nosort") != -1 || Sortable.trim(text) == '') continue;
      cell.table=table;
      cell.innerHTML = '<a href="#" class="sortheader" onclick="Sortable.sort(this.parentNode.table, \'key'+i+'\', this.reverse); this.reverse=!this.reverse ;return false;">↓'+text+'↑</a>'; //
    }
    // do it one time
    table.sortable=true;
    return true;
  },
  /**
   * build sortable key from element content
   */
  key: function(text) {
    if (typeof text == 'string');
    else if (text.hasAttribute("sort")) text=text.getAttribute("sort");
    else if (text.hasAttribute("data-sort")) text=text.getAttribute("data-sort");
    else if (text.textContent) text=text.textContent;
    else text = text.innerHTML.replace(/<.+>/g, '');
    // incredibly slow in chrome
    // else if (text.innerText) text=text.innerText;
    // return text.substring(0, 10) ;
    text=Sortable.trim(text);
    // num
    n=parseFloat(text.replace(/,/g, '.').replace(/[  ]/g, ''));
    // text
    if (isNaN(n)) {
      text=text.toLowerCase().replace(/\P{L}/g, '').replace(/œ/g, 'oe').replace(/æ/g, 'ae').replace(/ç/g, 'c').replace(/ñ/g, 'n').replace(/[éèêë]/g, 'e').replace(/[àâä]/g, 'a').replace(/[ïîí]/g, 'i').replace(/úûü/, 'u') +"__________";
      return text.substring(0, 10) ;
    }
    else {
      return n;
    }
  },
  /**
   * add alternate even odd classes on table rows
   */
  zebra: function (table) {
    for (var i = 0; i < table.tBodies.length; i++) {
      for (j=table.tBodies[i].rows.length -1; j >= 0; j--) this.paint(table.tBodies[i].rows[j], j);
    }
  },
  /**
   * Paint a row according to its index
   */
  paint: function(row, i) {
    row.className=" "+row.className+" ";
    row.className=row.className.replace(/ *(odd|even|mod5|mod10) */g, ' ');
    if ( (i % 2) == 0 ) row.className+=" even";
    if ( (i % 2) == 1 ) row.className+=" odd";
    if ( (i % 5) == 2 ) row.className+=" mod3";
    if ( (i % 5) == 0 ) row.className+=" mod5";
    if ( (i % 10) == 0 ) row.className+=" mod10";
    // row.className=row.className.replace(/^\s\s*|\s(?=\s)|\s\s*$/g, ""); // normalize-space, will bug a bit on \n\t
  },
  /**
   * A clever and fast trim, http://flesler.blogspot.com/2008/11/fast-trim-function-for-javascript.html
   */
  trim : function ( s ){
    var start = -1,
    end = s.length;
    while( s.charCodeAt(--end) < 33 );
    while( s.charCodeAt(++start) < 33 );
    return s.slice( start, end + 1 );
  }

}

// if loaded as bottom script, create tables
if(window.document.body) Sortable.load();
