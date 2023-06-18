function getQueryString(obj) {
  var queryStr = '';
  var first = true;

  for(var key in obj) {
    queryStr += (first ? '?': '&') + encodeURIComponent(key) + '=' + encodeURIComponent(obj[key])
    first = false;
  }

  return queryStr;
};

function handleSingleImage(img) {
  let dataSrc = img.getAttribute("data-src")
  if(!dataSrc) {
    return;
  }

  let queryObject = {"mode": "fit"};
  let width = img.getAttribute("data-width");

  if (width === null || width > window.innerWidth) {
    queryObject.width = window.innerWidth;
  } else {
    queryObject.width = width;
  }

  img.src = dataSrc + getQueryString(queryObject);
};

function processAllImages() {
  // images is 'HTMLCollection'
  var images = document.getElementsByTagName('img');
  var length = images.length;

  for(var i = 0; i<length ; i++) {
    handleSingleImage(images[i]);
  }
};
// RIC private tasks end

window.onload = function() {
  processAllImages();
}


