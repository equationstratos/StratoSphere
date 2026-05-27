

   var header = ('stratos');
 //  alert(header.charAt(0,1));
  // alert(header.slice(0,1));
 //  var s = $(this).text;
 //  alert(header.substr(0, 2))
   //alert(header.substring(1));
   //alert(header);








function toggleText1(myButton1) 
{
   var el = document.getElementById(myButton1);
   if (el.firstChild.data == "FLASH") 
   {
       el.firstChild.data = "NOFLASH";
       console.log("log flash");
       var flash=$("#MyButton1").val();
       var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (flash);
       alert(MyId,flash);
       var command = (flash);
       alert(command);
       
       var lst2 = document.getElementById('log').innerHTML
       lst2 = lst2.slice(-35);    
       alert(lst2);             
 
                    console.log(flash);                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update.php',
                        method:'POST',
                        data:{
                           MyId:MyId,
                           flash:flash,

                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });

                    $.ajax({

                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                            lst2:lst2,
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "FLASH";
     console.log("log noflash");
            var flash="NOFLASH";
            var command = (flash);
                    console.log(flash);
                    var MyId = document.getElementById('log2').innerHTML
                    var MyId = $.trim(MyId);
                    document.getElementById('log3').innerHTML = "";
                    document.getElementById('log3').innerHTML += (flash);
                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            flash:flash,
                   //         MyId:MyIdupdate.php
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                    
                    $.ajax({

                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });

   }
}
function toggleText2(myButton2) 
{
   var el = document.getElementById(myButton2);
   if (el.firstChild.data == "VIBRATE") 
   {
       el.firstChild.data = "NOVIBRATE";
       console.log("log VIBRATE");
       var vibrate=$("#MyButton2").val();
       var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (vibrate);
       alert(vibrate);
       var command = (vibrate);
                    console.log(vibrate);                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update2.php',
                        method:'POST',
                        data:{
                            vibrate:vibrate,
                            MyId:MyId,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                    $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "VIBRATE";
     console.log("log no vibrate");
            var vibrate="NOVIBRATE";
            var MyId = document.getElementById('log2').innerHTML
            var MyId = $.trim(MyId);
            document.getElementById('log3').innerHTML = "";
            document.getElementById('log3').innerHTML += (vibrate);
            alert(vibrate);
            var command = (vibrate);
                    console.log(vibrate);
                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update2.php',
                        method:'POST',
                        data:{
                            vibrate:vibrate,
                            MyId:MyId,
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                    $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}
function toggleText3(myButton3) 
{
   var el = document.getElementById(myButton3);
   if (el.firstChild.data == "RING") 
   {
       el.firstChild.data = "NORING";
       console.log("log RING");
       var ring=$("#MyButton3").val();
              var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (ring);
       alert(ring); 
       var command = (ring);
                    console.log(ring);                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update3.php',
                        method:'POST',
                        data:{
                            ring:ring,
                            MyId:MyId,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                        $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "RING";
     console.log("log noring");
            var ring="NORING";
                    console.log(ring);

                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (ring);
       alert(ring);
       var command= (ring);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update3.php',
                        method:'POST',
                        data:{
                            ring:ring,
                            MyId:MyId,
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                        $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}

function toggleText4(myButton4) 
{
   var el = document.getElementById(myButton4);
   if (el.firstChild.data == "PLAYAUDIO") 
   {
       el.firstChild.data = "NOPLAYAUDIO";
       console.log("log playaudio");
       var playaudio=$("#MyButton4").val();
              var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (playaudio);
       alert(playaudio);
       var command = (playaudio);

                    console.log(playaudio);                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update4.php',
                        method:'POST',
                        data:{
                            playaudio:playaudio,
                            MyId:MyId,
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                            $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "PLAYAUDIO";
     console.log("log noplayaudio");
            var playaudio="NOPLAYAUDIO";
                   var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (playaudio);
       alert(playaudio);
       var command = playaudio;
                    console.log(playaudio);
                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update4.php',
                        method:'POST',
                        data:{
                            playaudio:playaudio,
                            MyId:MyId,
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                            $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}

function toggleText5(myButton5) 
{
   var el = document.getElementById(myButton5);
   if (el.firstChild.data == "STROBO") 
   {
       el.firstChild.data = "NOSTROBO";
       console.log("log strobo");
       var strobo=$("#MyButton5").val();
                   var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (strobo);
                    console.log(strobo);  
                    var command = (strobo);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update5.php',
                        method:'POST',
                        data:{
                            strobo:strobo,
                            MyId:MyId,
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                            $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "STROBO";
     console.log("log nostrobo");
            var strobo="NOSTROBO";
                               var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (strobo);
                    console.log(strobo);
                    var command = (strobo);
                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update5.php',
                        method:'POST',
                        data:{
                            strobo:strobo,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                            $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}

function toggleText6(myButton6) 
{

   var el = document.getElementById(myButton6);
   if (el.firstChild.data == "MORSE") 
   {
       
               var x;
    var name=prompt("Text to traduce in morse");
    if (name!=null){
       x="ok send to device "+ name +"to traduce in morse code";
      alert(x);
   }
       
       el.firstChild.data = "NOMORSE";
       console.log("log morse");
       var morse=$("#MyButton6").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (morse); 

                    console.log(morse);  
                    var command = (morse);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update6.php',
                        method:'POST',
                        data:{
                            morse:morse,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                            $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "MORSE";
     console.log("log nomorse");
            var morse="NOMORSE";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (morse);
                    console.log(morse);
                    var command = (morse);
                    
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update6.php',
                        method:'POST',
                        data:{
                            morse:morse,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                            $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}



function toggleText7(myButton7) 
{
   var el = document.getElementById(myButton7);
   if (el.firstChild.data == "RECORDVIDEOFRONT") 
   {
       el.firstChild.data = "NORECORDVIDEOFRONT";
       console.log("log recordvideofront");
       var recordvideofront=$("#MyButton7").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (recordvideofront); 

                    console.log(recordvideofront);                     
                    var command = (recordvideofront);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update7.php',
                        method:'POST',
                        data:{
                            recordvideofront:recordvideofront,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "RECORDVIDEOFRONT";
     console.log("log nomorse");
            var recordvideofront="NORECORDVIDEOFRONT";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (recordvideofront);
                    
                    console.log(recordvideofront);
                    var command = (recordvideofront);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update7.php',
                        method:'POST',
                        data:{
                            recordvideofront:recordvideofront,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}

function toggleText8(myButton8) 
{
   var el = document.getElementById(myButton8);
   if (el.firstChild.data == "RECORDVIDEOBACK") 
   {
       el.firstChild.data = "NORECORDVIDEOBACK";
       console.log("log recordvideoback");
       var recordvideoback=$("#MyButton8").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (recordvideoback); 

                    console.log(recordvideoback);      
                    var command = (recordvideoback);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update8.php',
                        method:'POST',
                        data:{
                            recordvideoback:recordvideoback,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "RECORDVIDEOBACK";
     console.log("log nomorse");
            var recordvideoback="NORECORDVIDEOBACK";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (recordvideoback);
                    console.log(recordvideoback);
                    var command = (recordvideoback);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update8.php',
                        method:'POST',
                        data:{
                            recordvideoback:recordvideoback,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}
function toggleText9(myButton9) 
{
   var el = document.getElementById(myButton9);
   if (el.firstChild.data == "PICTUREFRONT") 
   {
       el.firstChild.data = "NOPICTUREFRONT";
       console.log("log localisation");
       var picturefront=$("#MyButton9").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (picturefront); 

                    console.log(picturefront);                    
                    var command = (picturefront);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update9.php',
                        method:'POST',
                        data:{
                            picturefront:picturefront,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "PICTUREFRONT";
     console.log("log localisation");
            var picturefront="NOPICTUREFRONT";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (picturefront);
                    console.log(picturefront);
                    var command = (picturefront);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update9.php',
                        method:'POST',
                        data:{
                            picturefront:picturefront,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}
function toggleText10(myButton10) 
{
   var el = document.getElementById(myButton10);
   if (el.firstChild.data == "PICTUREBACK") 
   {
       el.firstChild.data = "NOPICTUREBACK";
       console.log("log pictureback");
       var pictureback=$("#MyButton10").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (pictureback); 

                    console.log(pictureback);    
                    var command = (pictureback);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update10.php',
                        method:'POST',
                        data:{
                            pictureback:pictureback,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "PICTUREBACK";
     console.log("log pictureback");
            var pictureback="NOPICTUREBACK";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (pictureback);
                    console.log(pictureback);
                    var command = (pictureback);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update10.php',
                        method:'POST',
                        data:{
                            pictureback:pictureback,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}
function toggleText11(myButton11) 
{
   var el = document.getElementById(myButton11);
   if (el.firstChild.data == "LOCALISATION") 
   {
       el.firstChild.data = "NOLOCALISATION";
       console.log("log localisation");
       var localisation=$("#MyButton11").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (localisation); 

                    console.log(localisation);  
                    var command = (localisation);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update11.php',
                        method:'POST',
                        data:{
                            localisation:localisation,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "LOCALISATION";
     console.log("log localisation");
            var localisation="NOLOCALISATION";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (localisation);
                    console.log(localisation);
                    var command = (localisation);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update11.php',
                        method:'POST',
                        data:{
                            localisation:localisation,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}

function toggleText14(myButton14) 
{

   var el = document.getElementById(myButton14);
   if (el.firstChild.data == "TEXT2SPEACH") 
   {
       
               var x;
    var name=prompt("Text to traduce in morse");
    if (name!=null){
       x="ok send to device "+ name +"to traduce in morse code";
      alert(x);
   }
       
       el.firstChild.data = "NOTEXT2SPEACH";
       console.log("log morse");
       var text2speach=$("#MyButton14").val();
                                     var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (text2speach); 

                    console.log(text2speach);                    
                    var command = (text2speach);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update14.php',
                        method:'POST',
                        data:{
                            text2speach:text2speach,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
                                                                                $.ajax({
                        url:'command.php',
                        method:'POST',
                        data:{
                            MyId:MyId,
                            command:command,
                   //         MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
   else 
   {
     el.firstChild.data = "TEXT2SPEACH";
     console.log("log notext2speach");
            var text2speach="NOTEXT2SPEACH";
                                           var MyId = document.getElementById('log2').innerHTML
       var MyId = $.trim(MyId);
       document.getElementById('log3').innerHTML = "";
       document.getElementById('log3').innerHTML += (text2speach);
                    console.log(text2speach);
                    var command = (text2speach);
                    //var MyId=$("#MyId").val();
                    $.ajax({
                        url:'update14.php',
                        method:'POST',
                        data:{
                            text2speach,text2speach,
                            MyId:MyId
                        },
                        success:function(response){
                            alert(response);
                        }
                    });
   }
}




$(function() {
  const $rows = $("table tbody tr").on("click",function() {
    if ($("#el").is(":checked")) {
      console.log("hey");
      $rows.removeClass('highlight');
      $(this).addClass('highlight');
      
      
                 // var marker = L.marker([46.079722, 6.401389]).addTo(current_position);
      
         let ddd = $(this).text();
   //alert(ddd);
   
   var ddd2 = (ddd.substr(0, 20));
   console.log(ddd2);
 //  console.log($(this));
   //alert(ddd2)
   document.getElementById('log').innerHTML = "";

   document.getElementById('log').innerHTML += (ddd);
      document.getElementById('log2').innerHTML = "";

   document.getElementById('log2').innerHTML += (ddd2);
          var MyDiv1 = document.getElementById('log2').innerHTML
       var MyDiv1 = $.trim(MyDiv1);
       alert("You choose Id :"+MyDiv1);
       
       const lst2 = ddd.slice(-35);    


       alert(lst2);
       var checkboxes = document.getElementsByClassName("radioCheck");
        
        for(var i = 0; i < checkboxes.length; i++)
        {
            //uncheck all
            if(checkboxes[i].checked == true)
            {
                checkboxes[i].checked = false;
            }
        }
        
        var $checkbox = $(this).find('input');
        var isChecked = $checkbox.prop('checked');

        if (isChecked) {
            $checkbox.removeProp('checked');
        }
        else {
            $checkbox.prop('checked', 'checked');
        }   
    
        alert("heello");

      
      
      
    } else if ($("#el2").is(":checked")) {
      console.log("hey2");
      $(this).addClass('highlight');
      
               let ddd = $(this).text();
   //alert(ddd);
   
   var ddd2 = (ddd.substr(0, 20));
   console.log(ddd2);
 //  console.log($(this));
   //alert(ddd2)


   document.getElementById('log').innerHTML += (ddd);


   document.getElementById('log2').innerHTML += (ddd2);
          var MyDiv1 = document.getElementById('log2').innerHTML
       var MyDiv1 = $.trim(MyDiv1);
       alert("You choose Id :"+MyDiv1);
       
       const lst2 = ddd.slice(-35);    

      
       alert(lst2);
      
      
      var checkboxes = document.getElementsByClassName("radioCheck");
        

        
        var $checkbox = $(this).find('input');
        var isChecked = $checkbox.prop('checked');

        if (isChecked) {
            $checkbox.prop('checked');
        }
        else {
            $checkbox.prop('checked', 'checked');
        }   
    
        alert("heello2");
        

    }
  })
})





