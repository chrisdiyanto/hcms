// next_previouse_tab
// ============================================================================================================================
function next_tab1(){
    $('#tab1').removeClass('active');
    $('#tab1').removeAttr('href data-toggle');
    // $('#tab1').addClass('inactive_tab1');
    // menonaktifkan tab 
    $('#step1').removeClass('active');
    // menonaktifkan tabcontent tab1
    $('#tab2').removeClass('inactive_tab1');
    $('#tab2').addClass('active');
    $('#tab2').attr('href', '#step2' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab2').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step2').addClass('active in');
}

function previous_tab2(){
    $('#tab2').removeClass('active');
    $('#tab2').removeAttr('href data-toggle');
    // $('#tab2').addClass('inactive_tab1');
    // menonaktifkan tab 
    $('#step2').removeClass('active');
    // menonaktifkan tabcontent tab1
    $('#tab1').removeClass('inactive_tab1');
    $('#tab1').addClass('active');
    $('#tab1').attr('href', '#step1' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab1').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step1').addClass('active in');

}

function next_tab2(){
    $('#tab2').removeClass('active');
    $('#tab2').removeAttr('href data-toggle');
    // $('#tab2').addClass('inactive_tab2');
    // menonaktifkan tab 
    $('#step2').removeClass('active');
    // menonaktifkan tabcontent tab2
    $('#tab3').removeClass('inactive_tab2');
    $('#tab3').addClass('active');
    $('#tab3').attr('href', '#step3' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab3').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step3').addClass('active in');
}

function previous_tab3(){
    $('#tab3').removeClass('active');
    $('#tab3').removeAttr('href data-toggle');
    // $('#tab3').addClass('inactive_tab3');
    // menonaktifkan tab 
    $('#step3').removeClass('active');
    // menonaktifkan tabcontent tab3
    $('#tab2').removeClass('inactive_tab2');
    $('#tab2').addClass('active');
    $('#tab2').attr('href', '#step2' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab2').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step2').addClass('active in');

}

function next_tab3(){
    $('#tab3').removeClass('active');
    $('#tab3').removeAttr('href data-toggle');
    // $('#tab3').addClass('inactive_tab3');
    // menonaktifkan tab 
    $('#step3').removeClass('active');
    // menonaktifkan tabcontent tab3
    $('#tab4').removeClass('inactive_tab4');
    $('#tab4').addClass('active');
    $('#tab4').attr('href', '#step4' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab4').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step4').addClass('active in');

}

function previous_tab4(){
    $('#tab4').removeClass('active');
    $('#tab4').removeAttr('href data-toggle');
    // $('#tab4').addClass('inactive_tab4');
    // menonaktifkan tab 
    $('#step4').removeClass('active');
    // menonaktifkan tabcontent tab4
    $('#tab3').removeClass('inactive_tab3');
    $('#tab3').addClass('active');
    $('#tab3').attr('href', '#step3' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab3').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step3').addClass('active in');

}

function next_tab4(){
    $('#tab4').removeClass('active');
    $('#tab4').removeAttr('href data-toggle');
    // $('#tab4').addClass('inactive_tab4');
    // menonaktifkan tab 
    $('#step4').removeClass('active');
    // menonaktifkan tabcontent tab4
    $('#tab5').removeClass('inactive_tab5');
    $('#tab5').addClass('active');
    $('#tab5').attr('href', '#step5' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab5').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step5').addClass('active in');
}

function previous_tab5(){
    $('#tab5').removeClass('active');
    $('#tab5').removeAttr('href data-toggle');
    // $('#tab5').addClass('inactive_tab5');
    // menonaktifkan tab 
    $('#step5').removeClass('active');
    // menonaktifkan tabcontent tab5
    $('#tab4').removeClass('inactive_tab4');
    $('#tab4').addClass('active');
    $('#tab4').attr('href', '#step4' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab4').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step4').addClass('active in');

}

function next_tab5(){
    $('#tab5').removeClass('active');
    $('#tab5').removeAttr('href data-toggle');
    // $('#tab5').addClass('inactive_tab5');
    // menonaktifkan tab 
    $('#step5').removeClass('active');
    // menonaktifkan tabcontent tab5
    $('#tab6').removeClass('inactive_tab6');
    $('#tab6').addClass('active');
    $('#tab6').attr('href', '#step6' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab6').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step6').addClass('active in');
}

function previous_tab6(){
    $('#tab6').removeClass('active');
    $('#tab6').removeAttr('href data-toggle');
    // $('#tab6').addClass('inactive_tab6');
    // menonaktifkan tab 
    $('#step6').removeClass('active');
    // menonaktifkan tabcontent tab6
    $('#tab5').removeClass('inactive_tab5');
    $('#tab5').addClass('active');
    $('#tab5').attr('href', '#step5' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab5').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step5').addClass('active in');

}

function next_tab6(){
    $('#tab6').removeClass('active');
    $('#tab6').removeAttr('href data-toggle');
    // $('#tab6').addClass('inactive_tab6');
    // menonaktifkan tab 
    $('#step6').removeClass('active');
    // menonaktifkan tabcontent tab6
    $('#tab7').removeClass('inactive_tab7');
    $('#tab7').addClass('active');
    $('#tab7').attr('href', '#step7' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab7').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step7').addClass('active in');
}

function previous_tab7(){
    $('#tab7').removeClass('active');
    $('#tab7').removeAttr('href data-toggle');
    // $('#tab7').addClass('inactive_tab7');
    // menonaktifkan tab 
    $('#step7').removeClass('active');
    // menonaktifkan tabcontent tab7
    $('#tab6').removeClass('inactive_tab6');
    $('#tab6').addClass('active');
    $('#tab6').attr('href', '#step6' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab6').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step6').addClass('active in');

}

function next_tab7(){
    $('#tab7').removeClass('active');
    $('#tab7').removeAttr('href data-toggle');
    // $('#tab7').addClass('inactive_tab7');
    // menonaktifkan tab 
    $('#step7').removeClass('active');
    // menonaktifkan tabcontent tab6
    $('#tab8').removeClass('inactive_tab8');
    $('#tab8').addClass('active');
    $('#tab8').attr('href', '#step8' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab8').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step8').addClass('active in');
}

function previous_tab8(){
    $('#tab8').removeClass('active');
    $('#tab8').removeAttr('href data-toggle');
    // $('#tab8').addClass('inactive_tab8');
    // menonaktifkan tab 
    $('#step8').removeClass('active');
    // menonaktifkan tabcontent tab8
    $('#tab7').removeClass('inactive_tab7');
    $('#tab7').addClass('active');
    $('#tab7').attr('href', '#step7' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab7').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step7').addClass('active in');
}

function next_tab8(){
    $('#tab8').removeClass('active');
    $('#tab8').removeAttr('href data-toggle');
    // $('#tab8').addClass('inactive_tab8');
    // menonaktifkan tab 
    $('#step8').removeClass('active');
    // menonaktifkan tabcontent tab8
    $('#tab9').removeClass('inactive_tab9');
    $('#tab9').addClass('active');
    $('#tab9').attr('href', '#step9' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab9').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step9').addClass('active in');
}

function previous_tab9(){
    $('#tab9').removeClass('active');
    $('#tab9').removeAttr('href data-toggle');
    // $('#tab9').addClass('inactive_tab9');
    // menonaktifkan tab 
    $('#step9').removeClass('active');
    // menonaktifkan tabcontent tab9
    $('#tab8').removeClass('inactive_tab8');
    $('#tab8').addClass('active');
    $('#tab8').attr('href', '#step8' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab8').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step8').addClass('active in');
}

function next_tab9(){
    $('#tab9').removeClass('active');
    $('#tab9').removeAttr('href data-toggle');
    // $('#tab9').addClass('inactive_tab9');
    // menonaktifkan tab 
    $('#step9').removeClass('active');
    // menonaktifkan tabcontent tab9
    $('#tab10').removeClass('inactive_tab10');
    $('#tab10').addClass('active');
    $('#tab10').attr('href', '#step10' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab10').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step10').addClass('active in');
}

function previous_tab10(){
    $('#tab10').removeClass('active');
    $('#tab10').removeAttr('href data-toggle');
    // $('#tab10').addClass('inactive_tab10');
    // menonaktifkan tab 
    $('#step10').removeClass('active');
    // menonaktifkan tabcontent tab10
    $('#tab9').removeClass('inactive_tab9');
    $('#tab9').addClass('active');
    $('#tab9').attr('href', '#step9' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab9').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step9').addClass('active in');
}

function next_tab10(){
    $('#tab10').removeClass('active');
    $('#tab10').removeAttr('href data-toggle');
    // $('#tab10').addClass('inactive_tab10');
    // menonaktifkan tab 
    $('#step10').removeClass('active');
    // menonaktifkan tabcontent tab10
    $('#tab11').removeClass('inactive_tab11');
    $('#tab11').addClass('active');
    $('#tab11').attr('href', '#step11' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab11').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step11').addClass('active in');
}

function previous_tab11(){
    $('#tab11').removeClass('active');
    $('#tab11').removeAttr('href data-toggle');
    // $('#tab11').addClass('inactive_tab11');
    // menonaktifkan tab 
    $('#step11').removeClass('active');
    // menonaktifkan tabcontent tab11
    $('#tab10').removeClass('inactive_tab10');
    $('#tab10').addClass('active');
    $('#tab10').attr('href', '#step10' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab10').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step10').addClass('active in');
}

function next_tab11(){
    $('#tab11').removeClass('active');
    $('#tab11').removeAttr('href data-toggle');
    // $('#tab11').addClass('inactive_tab11');
    // menonaktifkan tab 
    $('#step11').removeClass('active');
    // menonaktifkan tabcontent tab11
    $('#tab12').removeClass('inactive_tab12');
    $('#tab12').addClass('active');
    $('#tab12').attr('href', '#step12' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab12').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step12').addClass('active in');
}

function previous_tab12(){
    $('#tab12').removeClass('active');
    $('#tab12').removeAttr('href data-toggle');
    // $('#tab12').addClass('inactive_tab12');
    // menonaktifkan tab 
    $('#step12').removeClass('active');
    // menonaktifkan tabcontent tab12
    $('#tab11').removeClass('inactive_tab11');
    $('#tab11').addClass('active');
    $('#tab11').attr('href', '#step11' );
    // menambahkan attribut link tab ke tab content yang dituju
    $('#tab11').attr('data-toggle', 'tab');
    // menambah atrribut data data-toggle
    $('#step11').addClass('active in');
}
// ============================================================================================================================    
// next_previouse_tab