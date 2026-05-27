//refresh.js

$(document).ready(function() {


    var current_position = new L.featureGroup();
    //Map attribution
    var map_attr = "";

    var current_position2 = new L.featureGroup();
    //Map attribution
    var map_attr = "";

    var current_position3 = new L.featureGroup();
    //Map attribution
    var map_attr = "";

    // OpenStreetMap tiles
    var osmURL = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    var osm = L.tileLayer(osmURL, {attribution: map_attr});


    //Google Maps (Hybrid) maptile

    var gmhURL = "https://{s}.google.com/vt/lyrs=s,h&x={x}&y={y}&z={z}";
    var gmHybrid = L.tileLayer(gmhURL, {
        maxZoom: 20,
        subdomains: ["mt0","mt1","mt2","mt3"]
    })
var Esri_WorldImagery = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
	attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
});
   var Jawg_Matrix = L.tileLayer('https://{s}.tile.jawg.io/jawg-matrix/{z}/{x}/{y}{r}.png');


var NASA = L.tileLayer('https://map1.vis.earthdata.nasa.gov/wmts-webmerc/VIIRS_CityLights_2012/default/{time}/{tilematrixset}{maxZoom}/{z}/{y}/{x}.{format}', {
	attribution: 'Imagery provided by services from the Global Imagery Browse Services (GIBS), operated by the NASA/GSFC/Earth Science Data and Information System (<a href="https://earthdata.nasa.gov">ESDIS</a>) with funding provided by NASA/HQ.',
	bounds: [[-85.0511287776, -179.999999975], [85.0511287776, 179.999999975]],
	minZoom: 1,
	maxZoom: 8,
	format: 'jpg',
	time: '',
	tilematrixset: 'GoogleMapsCompatible_Level'
});

var DarkMatterNoLabels = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_nolabels/{z}/{x}/{y}{r}.png', {
	attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
	subdomains: 'abcd',
	maxZoom: 20
});
var Jawg_Matrix = L.tileLayer('https://{s}.tile.jawg.io/jawg-matrix/{z}/{x}/{y}{r}.png?access-token={accessToken}', {
	attribution: '<a href="http://jawg.io" title="Tiles Courtesy of Jawg Maps" target="_blank">&copy; <b>Jawg</b>Maps</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
	minZoom: 0,
	maxZoom: 22,
	subdomains: 'abcd',
	accessToken: 'Y8iHKqcdpcGeTZFDhLrPJ8QalJbdJy7aOHTe4U3A2xm7pg09G7CesfXrSSJTBTuc'
});

var OpenTopoMap = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
	maxZoom: 17,
	attribution: 'Map data: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, <a href="http://viewfinderpanoramas.org">SRTM</a> | Map style: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
});

    var map = L.map('mymap',{
        center: [45,45],
        zoom: 1.15,
        fullscreenControl: true, 

	      

        layers: [gmHybrid, current_position, current_position2, current_position3]
    });
    


    var baseLayers = {
        "OpenStreet Map": osm,
        "Google Map (Hybrid)": gmHybrid,
        "worldImagery" : Esri_WorldImagery,
        "Nasa" : NASA,
        "Black" : DarkMatterNoLabels,
        "Matrix" :Jawg_Matrix,
        "Topo" :OpenTopoMap
    }; 

    var overlays = {"Current GPS":current_position,
                    "Current GPS2":current_position2,
                    "Current GPS3":current_position3,
    };

    //Draw all layers
    L.control.layers(baseLayers, overlays, {collapsed:true}).addTo(map);
//////////////////////////////////////////





     var map,
          
          ll = new L.LatLng(-36.852668, 174.762675),
          ll2 = new L.LatLng(-36.86, 174.77);

      function showCoordinates (e) {
	      alert(e.latlng);
      }

      function centerMap (e) {
	      map.panTo(e.latlng);
      }

      function zoomIn (e) {
	      map.zoomIn();
      }

      function zoomOut (e) {
	      map.zoomOut();
      }

      map = L.map('map', {
	      center: ll,
	      zoom: 15,
	      contextmenu: true,
      contextmenuWidth: 140,
	      contextmenuItems: [{
		      text: 'Show coordinates',
		      callback: showCoordinates
	      }, {
		      text: 'Center map here',
		      callback: centerMap
	      }, '-', {
		      text: 'Zoom in',
		      icon: 'images/zoom-in.png',
		      callback: zoomIn
	      }, {
		      text: 'Zoom out',
		      icon: 'images/zoom-out.png',
		      callback: zoomOut
	  }]
      });

	  L.tileLayer('http://{s}.tile.osm.org/{z}/{x}/{y}.png', {
		  attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors'
	  }).addTo(map);

      L.marker(ll, {
          contextmenu: true,
          contextmenuItems: [{
              text: 'Marker item',
              index: 0
          }, {
              separator: true,
              index: 1
          }]
      }).addTo(map);

      L.marker(ll2, {
          contextmenu: true,
          contextmenuInheritItems: false,
          contextmenuItems: [{
              text: 'Marker item'
          }]
      }).addTo(map);








// Add marker
    reloadMarker()
//deleteMarker();
    //FUNCTION TO ADD AND RELOAD MARKERS
    function reloadMarker(){
        //Get current position data from JSON (from data.php)
        $.getJSON("./dataMarkers.php",function(mypos){
            //test 
            console.log(mypos);
            let counter = 0;            
            for(var i=0; i<mypos.length; i++){
            console.log(counter);
            counter++;

                var stnMarker =
                 L.marker([mypos[i].latitude,mypos[i].longitude]).addTo(current_position).on('click', onClick).bindTooltip(((mypos[i].fid)+("<br/>")+ (mypos[i].brandname) +("<br/>")+ (mypos[i].modelname))).bindPopup(((mypos[i].fid)+("<br/>")+ (mypos[i].brandname) +("<br/>")+ (mypos[i].modelname)));
                var circle = L.circle([mypos[i].latitude,mypos[i].longitude], {
            color: 'red',
            fillColor: '#f03',
            fillOpacity: 0.1,
            radius: 20
        }).addTo(current_position3);
        
                //stnMarker._icon.classList.add("huechange"+counter);

                                console.log(counter);
                                           
                //setTimeout(() => {stnMarker.remove();; }, 5000);


$(function() {
  const $rows = $("table tbody tr").on("click",function() {
       // map.removeLayer(popup);
        let ddd = $(this).text();
        var ddd2 = (ddd.substr(0, 20));
        var ddd3 = (ddd2.trim());
        console.log(ddd3);
        
        
        const longitude11 = ddd.slice(-35);    
        const lst3 = ddd.slice(-58);  
        const longitude22 = longitude11.trim();
        lst4 = lst3.trim();
        const latitude22 = lst4.slice(-30,-18);
        console.log(latitude22);
        console.log(longitude22);
        

        var popup = L.marker([latitude22, longitude22]).bindPopup(ddd3).addTo(current_position).openPopup();
        console.log("lovejava");
        popup._icon.classList.add("huechangeSELECTED");
        map.panTo([latitude22, longitude22]);
     // current_position.removeLayer(popup);
        setTimeout(() => { current_position.removeLayer(popup);; }, 2500);
        setTimeout(() => { popup.addTo(current_position);; }, 5000);
        setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 5000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 7500);
        setTimeout(() => { popup.addTo(current_position);; }, 10000);
                setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 10000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 12500);
        setTimeout(() => { popup.addTo(current_position);; }, 15000);
                setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 15000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 17500);
        setTimeout(() => { popup.addTo(current_position);; }, 20000);
                setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 20000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 22500);
        setTimeout(() => { popup.addTo(current_position);; }, 25000);
                setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 25000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 27500);
        setTimeout(() => { popup.addTo(current_position);; }, 30000);
                setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 30000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 32500);
        setTimeout(() => { popup.addTo(current_position);; }, 35000);
                setTimeout(() => { popup._icon.classList.add("huechangeSELECTED");; }, 35000);
        setTimeout(() => { current_position.removeLayer(popup);; }, 37500);

     //.remove();
     //  popup.openPopup();            
            })
            });

     function onClick(e) {
     
           // var marker = L.popup([46.079722, 6.401389]).setContent('<p>Hello world!<br />This is a nice popup.</p>').addTo(current_position);
   var popupkpkp = e.target.getPopup();
   var content = popupkpkp.getContent();
   content2 = content.split('<')[0]
   console.log(content2);
   map.panTo(e.latlng);

   document.getElementById('log').innerHTML = "";
   document.getElementById('log').innerHTML += (content);
   
   document.getElementById('log2').innerHTML = "";
   document.getElementById('log2').innerHTML += (content2);
   
   $("table tbody tr").removeClass('highlight');
   $("table tbody tr").filter(function(){
   return $.trim($('td', this).eq(1).text())==(content2);
}).addClass('highlight');

    alert(this.getLatLng());
    

        //uncheck all
        var checkboxes = document.getElementsByClassName("radioCheck");
        
        for(var i = 0; i < checkboxes.length; i++)
        {
            
            if(checkboxes[i].checked == true)
            {
                checkboxes[i].checked = false;
            }
        }
        //check the marker's row selected
        var $checkbox = $("table tbody tr").filter(function(){
   return $.trim($('td', this).eq(1).text())==(content2);
}).find('input');

        var isChecked = $checkbox.prop('checked');

        if (isChecked) {
            $checkbox.removeProp('checked');
        }
        else {
            $checkbox.prop('checked', 'checked');
        }       
}
}                            
});  
    }
});
