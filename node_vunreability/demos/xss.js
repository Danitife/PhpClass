/**
 * XSS noticeboard lesson. Run: node demos/xss.js
 * Browser handout: node demos/xss.js --html > /tmp/xss-noticeboard.html
 */

const sampleFlyer = 'Catering this Saturday <strong>Free samples</strong> & bookings';
const savedFlyers = ['The community market opens at 9 am.', sampleFlyer];
const [mode, searchTerm = sampleFlyer] = process.argv.slice(2);

const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

function escapeHtmlText(text) {
  return text.replace(/[&<>"']/g, character => entities[character]);
}

// Stored example. A later page render reads the previously saved flyers.
const flyerItems = savedFlyers.map(flyer => `<li>${escapeHtmlText(flyer)}</li>`).join('\n');

// Reflected example. The command-line argument stands in for a search request.
// The generated page displays it in the same response.
const searchResult = `<p>Search term <code>${escapeHtmlText(searchTerm)}</code></p>`;

if (mode === '--html') {
  console.log(`<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>XSS and the community noticeboard</title>
  <style>
    body { max-width: 44rem; margin: 2rem auto; padding: 0 1rem; font: 1rem/1.55 system-ui, sans-serif; }
    section { margin-block: 1.5rem; }
    textarea { box-sizing: border-box; display: block; width: 100%; padding: .5rem; font: inherit; }
    code, #draft-preview { overflow-wrap: anywhere; }
  </style>
</head>
<body>
<main>
  <h1>XSS and the community noticeboard</h1>
  <p>Cross-site scripting happens when untrusted content becomes active code in a trusted page. This noticeboard uses harmless HTML-like text to show three places where an application must keep input as data.</p>
  <section>
    <h2>Stored XSS</h2>
    <p>The page reads flyers from a list of saved posts. The server encodes each flyer as HTML text when it displays the list.</p>
    <ul>${flyerItems}</ul>
  </section>
  <section>
    <h2>Reflected XSS</h2>
    <p>The search term appears in the response to the same request. This generated page uses the search term passed to the Node command.</p>
    ${searchResult}
  </section>
  <section>
    <h2>DOM-based XSS</h2>
    <p>The browser updates the draft preview as you type. Try angle brackets and an ampersand. The preview should show them literally.</p>
    <label for="draft">Draft flyer</label>
    <textarea id="draft" rows="2">${escapeHtmlText(sampleFlyer)}</textarea>
    <p>Preview <output id="draft-preview" for="draft"></output></p>
  </section>
</main>
<script>
  const draft = document.getElementById('draft');
  const preview = document.getElementById('draft-preview');

  function renderDraft() {
    preview.textContent = draft.value;
  }

  draft.addEventListener('input', renderDraft);
  renderDraft();
</script>
</body>
</html>`);
} else {
  console.log('XSS and the community noticeboard\n');
  console.log('Stored input comes from a saved post. The encoded list items are shown below.');
  console.log(flyerItems);
  console.log('\nReflected input comes from the current search request. The encoded response is shown below.');
  console.log(searchResult);
  console.log('\nDOM-based XSS involves browser code inserting input into the page. The preview uses textContent.');
  console.log('Generate the browser demo with node demos/xss.js --html > /tmp/xss-noticeboard.html');
}
