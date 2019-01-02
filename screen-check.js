window.onload = function() {

  var formats = [
    ['webp', 'webp'],
    ['jpeg', 'jpg'],
    ['png', 'png'],
    ['bmp', 'bmp']
  ];
  fmt = '';
  for (var i = 0; i < formats.length; i++) {
    if (supportsIMG(formats[i][0])) {
      fmt = '.' + formats[i][1];
      break;
    }
  }

//check if browser supports image format
  function supportsIMG(format) {
    var canvas = document.createElement('canvas');
    canvas.width = canvas.height = 1;
    var uri = canvas.toDataURL('image/' + format);

    return uri.match('image/' + format) !== null;
  }

  var height = screen.height;
  console.log(screen.height)
  console.log(screen.width)
}


