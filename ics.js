/*
 * banner_ics plugin
 * @author pulsejet
 */

window.rcmail && rcmail.addEventListener('init', function(evt) {
  $('.ics-accept').click(function () {
    console.log('accept')
  });
  $('.ics-decline').click(function () {
    console.log('decline')
  });
});

