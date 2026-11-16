// $(document).ready(function(){
//   // reform();

// }); /*end doc ready*/

// function reform(){
//     var data = [];
//     var page = $("#page").val();
//     $.ajax({
//       url   : BASE_URL + 'web/reform',
//       type  : 'POST',
//       data  : data,
//       success: function(json)
//         {
//           var obj = tryjson(respon);

//           if(obj.success === true){
//             $("#__c0s4WDdCcFUxTFNlbGtKQUxZZTM2dUh_").val(obj.__c0s4WDdCcFUxTFNlbGtKQUxZZTM2dUh_);
//           }
          
//         }
//     });
// }
    
function empty(e) {
    switch (e) {
        case NaN:
        case "":
        case 0:
        case "0":
        case null:
        case false:
        case typeof(e) == "undefined":
        case typeof(e) === "undefined":
        return true;
        default:
        return false;
    }
}

function peringatan(message, errors){
  let timerInterval;
  Swal.fire({
    title: message,
    text: errors,
    type: "warning",
    dangerMode: true,
    timer: 3000,
    timerProgressBar: true,
    didOpen: () => {
      Swal.showLoading()
      timerInterval = setInterval(() => {
        const content = Swal.getContent()
        if (content) {
          const b = content.querySelector('b')
          if (b) {
            b.textContent = Swal.getTimerLeft()
          }
        }
      }, 100)
    },
    willClose: () => {
      clearInterval(timerInterval)
    }
  })
  // reform();
}

function sukses(message){
  swal({
    title: message,
    timer: 3000,
  })
}

function generateform(data){
    var formData = new FormData();
    $.each(data, function() {
      formData.append(this.name, this.value);
    });

    return formData;
}

function logindl(){
  peringatan('Maaf', 'Anda harus login terlebih dahulu');
  $('#modal_login').modal('show');
}

function login(){
  $('#modal_login').modal('show');
}

function ubahpassword(){
  $('#ubah_password').modal('show');
}

function formatRupiah(money){
  return new Intl.NumberFormat("id-ID", { style: "currency", currency: "IDR" }).format(money);
}

function formatinput(el){
  if ($(el).val() !== "") {
    var n = parseInt($(el).val().replace(/\D/g,''),10);
    $(el).val(n.toLocaleString("id-ID"));
  }
}

 (function() {
    'use strict';
    window.addEventListener('load', function() {
      // Fetch all the forms we want to apply custom Bootstrap validation styles to
      var forms = document.getElementsByClassName('needs-validation');
      // Loop over them and prevent submission
      var validation = Array.prototype.filter.call(forms, function(form) {
        form.addEventListener('submit', function(event) {
          if (form.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
          }
          form.classList.add('was-validated');
        }, false);
      });
    }, false);
  })();

  function tryjson(respon){
    
    var json = respon;
    try {
       obj = JSON.parse(json);
    }
    catch(err) {
       obj = JSON.parse(JSON.stringify(json));
    }

    return obj;
 }

  

  function resetData(data){
    while(data.length > 0){
      data.pop();
    }
  }

 function cetak(){
    window.print();
 }

 function searchclear(){
    $('#filter').clearSearch();
    $('#field').clearSearch();
 }

 
  function cleanform(){
    $(".formajax").html("<div></div>");
  }

 function typeWriter(id, i, txt) {
  if(!empty(txt)){
    var speed = 75;
  
    if (i < txt.length) {
            document.getElementById(id).innerHTML += txt.charAt(i);
            i++;
            
            setTimeout(function (){typeWriter(id, i, txt)}, speed);
         }
  }
}

function typewriterLostEffect(element, array) {
  let currentArrayIndex = 0;
  let currentText = '';
  
  function type() {
     if (currentArrayIndex < array.length ) {
       
       const currentItem = array[currentArrayIndex]; // array index ke ...

        if (typeof currentItem === 'string') {

          currentLi = element + currentArrayIndex; // pendefinisian <div id =""></div> yg akan dituju
          
          currentText = currentItem.substring(0, currentText.length + 1); // text dalam array ke ..

          document.getElementById(currentLi).innerHTML = `<li>${currentText}</li>`;

        } else {
          document.getElementById(currentLi).innerHTML = currentItem;
        }

        if (currentText === currentItem) {
        currentArrayIndex++;
        currentText = '';
        }

        setTimeout(type, 75);
     }
  }

  type();
}

function typewriterListEffect(element, array) {
  let currentArrayIndex = 0;
  let currentText = '';
  
  function type() {
     if (currentArrayIndex < array.length ) {

      if(array[currentArrayIndex].includes("<li>")){
        var newtext0 = array[currentArrayIndex].replace('<li>',"");
        var newtex = newtext0.replace("</li>","");

        const currentItem = newtex; // array index ke ...

        if (typeof currentItem === 'string') {

          currentLi = element + currentArrayIndex; // pendefinisian <div id =""></div> yg akan dituju
          
          currentText = currentItem.substring(0, currentText.length + 1); // text dalam array ke ..

          document.getElementById(currentLi).innerHTML = `<li>${currentText}</li>`;

        } else {
          document.getElementById(currentLi).innerHTML = currentItem;
        }

        
        if (currentText === currentItem) {
          currentArrayIndex++;
          currentText = '';
          }
      }else{

        const currentItem = array[currentArrayIndex]; // array index ke ...

        if (typeof currentItem === 'string') {

          currentLi = element + currentArrayIndex; // pendefinisian <div id =""></div> yg akan dituju
          
          currentText = currentItem.substring(0, currentText.length + 1); // text dalam array ke ..

          document.getElementById(currentLi).innerHTML = `${currentText}`;

        } else {
          document.getElementById(currentLi).innerHTML = currentItem;
        }

        
        if (currentText === currentItem) {
          currentArrayIndex++;
          currentText = '';
          }
      }


        setTimeout(type, 75);
     }
  }

  type();
}

