// Runs the plugin's inline browser script in jsdom with a fake fetch and clipboard.
// Usage (from the repo root):
//   docker run --rm -v "$PWD":/app -w /app/tests/js node:22-slim sh -c "npm install --no-audit --no-fund --silent && node run.js"
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const php = fs.readFileSync(path.join(__dirname, '../../avitar-explainer/avitar-explainer.php'), 'utf8');
const fnStart = php.indexOf('function avitar_explainer_print_script');
const script = php.slice(php.indexOf('<script>', fnStart) + '<script>'.length, php.indexOf('</script>', fnStart));

let checks = 0;
let failures = 0;
function check(name, condition) {
	checks++;
	if (condition) {
		console.log('  ok   ' + name);
	} else {
		failures++;
		console.log('  FAIL ' + name);
	}
}

function reply(status, body) {
	return Promise.resolve({
		ok: status >= 200 && status < 300,
		status: status,
		json: () => (body === undefined ? Promise.reject(new SyntaxError('Unexpected token <')) : Promise.resolve(body)),
	});
}

const SAMPLE = {
	explanation: 'raw text',
	items: [
		{ segment: 'VIEW POT GOOD TOPO', meaning: 'Good view and topography.', uncertain: [] },
		{ segment: 'OB PONDSIDE', meaning: 'An outbuilding by the pond.', uncertain: ['OB is not a standard code.'] },
	],
	summary: 'A pondside lot.',
	extra: [],
};

// explain: function returning the explain reply; nonce: optional override.
function page({ explain, nonce, clipboard }) {
	const dom = new JSDOM(
		'<div class="avitar-explainer" data-rest="http://site.test/wp-json/avitar-explainer/v1/">' +
			'<textarea>CU*LLA</textarea><button type="button" class="avitar-explainer__button">Explain</button>' +
			'<div class="avitar-explainer__result" aria-live="polite"></div></div>',
		{ runScripts: 'outside-only', url: 'http://site.test/' }
	);
	const w = dom.window;
	const calls = { fetch: [], execCommand: [], clipboard: [], scroll: [] };
	const errors = [];
	w.fetch = (url, options) => {
		calls.fetch.push(url);
		if (url.endsWith('nonce')) {
			return nonce ? nonce() : reply(200, { nonce: 'abc' });
		}
		return explain();
	};
	w.console.error = (...args) => errors.push(args.map(String).join(' '));
	w.document.execCommand = (command) => {
		calls.execCommand.push(command);
		return true;
	};
	if (clipboard) {
		w.ClipboardItem = class {
			constructor(items) {
				this.items = items;
			}
		};
		Object.defineProperty(w.navigator, 'clipboard', {
			configurable: true,
			value: {
				write: (items) => {
					calls.clipboard.push(items);
					return clipboard === 'reject' ? Promise.reject(new Error('denied')) : Promise.resolve();
				},
			},
		});
	}
	w.scrollTo = (options) => calls.scroll.push(options);
	Object.defineProperty(w, 'innerHeight', { configurable: true, value: 800 });
	Object.defineProperty(w, 'scrollY', { configurable: true, value: 300 });
	w.document.querySelector('.avitar-explainer__result').getBoundingClientRect = () => ({ top: 1200 });
	w.eval(script);
	const doc = w.document;
	return {
		w,
		doc,
		calls,
		errors,
		result: doc.querySelector('.avitar-explainer__result'),
		button: doc.querySelector('.avitar-explainer__button'),
	};
}

async function settle(p) {
	for (let i = 0; i < 200 && (p.button.disabled || p.button.textContent !== 'Explain'); i++) {
		await new Promise((resolve) => setTimeout(resolve, 0));
	}
}

async function tick() {
	for (let i = 0; i < 20; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0));
	}
}

function readBlob(w, blob) {
	return new Promise((resolve) => {
		const reader = new w.FileReader();
		reader.onload = () => resolve(reader.result);
		reader.readAsText(blob);
	});
}

async function main() {
	console.log('explanation rendering');
	let p = page({ explain: () => reply(200, SAMPLE) });
	p.button.click();
	check('working message is outlined', p.result.classList.contains('is-working'));
	check('working message gives the measured wait time', p.result.textContent === 'Reviewing the notes. This usually takes 30 to 40 seconds.');
	await settle(p);
	check('finished explanation uses the done style', p.result.classList.contains('is-done') && !p.result.classList.contains('is-working'));
	check('heading reads "Notes for this property"', p.result.querySelector('h3').textContent === 'Notes for this property');
	const items = p.result.querySelectorAll('ol > li');
	check('one list item per segment', items.length === 2);
	check('segment is bold without quotes', items[0].querySelector('strong').textContent === 'VIEW POT GOOD TOPO');
	check('uncertain line is marked', items[1].querySelector('.avitar-explainer__uncertain').textContent === '⚠ Uncertain: OB is not a standard code.');
	const summaryNode = p.result.querySelector('.avitar-explainer__summary');
	check('summary is shown', !!summaryNode && summaryNode.textContent === 'Summary: A pondside lot.');
	check('summary comes right after the heading, before the list', !!summaryNode && summaryNode.previousElementSibling.tagName === 'H3' && summaryNode.nextElementSibling.tagName === 'OL');
	check('explanation top is scrolled to the middle of the window', p.calls.scroll.length === 1 && p.calls.scroll[0].top === 300 + 1200 - 400);
	check('scrolling is smooth', p.calls.scroll.length === 1 && p.calls.scroll[0].behavior === 'smooth');
	check('response time shown', /^Response time: \d+\.\d s$/.test(p.result.querySelector('.avitar-explainer__time').textContent));

	console.log('copy button');
	const copy = p.result.querySelector('button.avitar-explainer__copy');
	check('copy button is in the explanation', !!copy && copy.getAttribute('aria-label') === 'Copy explanation');
	check('copy button shows an icon', !!copy && !!copy.querySelector('svg'));

	p = page({ explain: () => reply(200, SAMPLE), clipboard: 'ok' });
	p.button.click();
	await settle(p);
	p.result.querySelector('.avitar-explainer__copy').click();
	await tick();
	const item = p.calls.clipboard[0] && p.calls.clipboard[0][0];
	check('copy writes to the clipboard', !!item);
	const html = item ? await readBlob(p.w, item.items['text/html']) : '';
	const text = item ? await readBlob(p.w, item.items['text/plain']) : '';
	check('copied HTML keeps the heading and bold segments', html.includes('Notes for this property') && html.includes('<strong>VIEW POT GOOD TOPO</strong>'));
	check('copied HTML puts the summary before the list', html.indexOf('A pondside lot.') > -1 && html.indexOf('A pondside lot.') < html.indexOf('<ol'));
	check('copied HTML keeps the numbered list', html.includes('<ol') && html.includes('<li'));
	check('copied HTML styles uncertain lines inline', /<p style="[^"]*italic[^"]*">⚠ Uncertain: OB/.test(html));
	check('copied HTML leaves out the copy button and response time', !html.includes('<button') && !html.includes('<svg') && !html.includes('Response time'));
	check('copied plain text is a readable numbered list', text.startsWith('Notes for this property\n\nSummary: A pondside lot.\n\n1. VIEW POT GOOD TOPO\nGood view and topography.') && text.includes('⚠ Uncertain: OB is not a standard code.'));
	check('button confirms the copy', p.result.querySelector('.avitar-explainer__copy').getAttribute('aria-label') === 'Copied');

	p = page({ explain: () => reply(200, SAMPLE) });
	p.button.click();
	await settle(p);
	p.result.querySelector('.avitar-explainer__copy').click();
	await tick();
	check('without the Clipboard API, copy falls back to a styled selection', p.calls.execCommand[0] === 'copy');
	check('fallback leaves no temporary node behind', p.doc.querySelectorAll('.avitar-explainer__copy-buffer').length === 0);

	p = page({ explain: () => reply(200, SAMPLE), clipboard: 'reject' });
	p.button.click();
	await settle(p);
	p.result.querySelector('.avitar-explainer__copy').click();
	await tick();
	check('a refused clipboard write falls back to the selection copy', p.calls.execCommand[0] === 'copy');

	console.log('Enter key');
	function press(p, init) {
		const event = new p.w.KeyboardEvent('keydown', Object.assign({ key: 'Enter', bubbles: true, cancelable: true }, init));
		p.doc.querySelector('textarea').dispatchEvent(event);
		return event;
	}
	p = page({ explain: () => reply(200, SAMPLE) });
	let event = press(p, {});
	check('Enter in the text box sends the notes like clicking Explain', p.calls.fetch.length === 1 && p.button.disabled);
	check('Enter does not add a line break', event.defaultPrevented);
	event = press(p, {});
	await settle(p);
	check('Enter while working does not send the notes twice', p.calls.fetch.filter((url) => url.endsWith('explain')).length === 1);
	check('a second Enter while working adds no line break either', event.defaultPrevented);

	p = page({ explain: () => reply(200, SAMPLE) });
	event = press(p, { shiftKey: true });
	check('Shift+Enter still inserts a line break', p.calls.fetch.length === 0 && !event.defaultPrevented);
	event = press(p, { isComposing: true });
	check('Enter while composing text (e.g. an IME) is left alone', p.calls.fetch.length === 0 && !event.defaultPrevented);
	event = press(p, { key: 'a' });
	check('other keys type normally', p.calls.fetch.length === 0 && !event.defaultPrevented);

	p = page({ explain: () => reply(200, SAMPLE) });
	p.doc.querySelector('textarea').value = '   ';
	press(p, {});
	check('Enter with an empty box shows the same prompt as the button', p.result.textContent === 'Please paste some tax-card notes first.' && p.calls.fetch.length === 0);

	console.log('diagnostics');
	p = page({ explain: () => Promise.reject(new TypeError('NetworkError when attempting to fetch resource.')) });
	p.button.click();
	await settle(p);
	check('lost connection names the waiting step', p.result.classList.contains('is-error') && p.result.textContent.includes('while waiting for the explanation'));
	check('lost connection shows the browser error', p.result.querySelector('.avitar-explainer__details').textContent.includes('TypeError: NetworkError when attempting to fetch resource.'));
	check('error details include the elapsed time', /after \d+\.\d s/.test(p.result.querySelector('.avitar-explainer__details').textContent));
	check('error is logged to the console with its step', p.errors.length === 1 && p.errors[0].includes('explain'));

	p = page({ explain: () => reply(200, SAMPLE), nonce: () => reply(403, { message: 'Forbidden' }) });
	p.button.click();
	await settle(p);
	check('failed nonce names the security-token step', p.result.textContent.includes('security token'));
	check('failed nonce shows the HTTP status', p.result.querySelector('.avitar-explainer__details').textContent.includes('HTTP 403'));
	check('failed nonce does not call explain', p.calls.fetch.length === 1);

	p = page({ explain: () => reply(200, { explanation: 'x', items: 'not a list', summary: '', extra: [] }) });
	p.button.click();
	await settle(p);
	check('a drawing error is reported as such, not as a connection problem', p.result.textContent.includes('showing the explanation') && !p.result.textContent.includes('waiting for'));

	p = page({ explain: () => reply(502) });
	p.button.click();
	await settle(p);
	check('non-JSON error page shows the HTTP status', p.result.classList.contains('is-error') && p.result.querySelector('.avitar-explainer__details').textContent.includes('HTTP 502'));

	p = page({ explain: () => reply(429, { message: 'Too many requests from your network.' }) });
	p.button.click();
	await settle(p);
	check('server messages are still shown to the visitor', p.result.textContent.includes('Too many requests from your network.'));
	check('errors do not scroll the page', p.calls.scroll.length === 0);

	p = page({ explain: () => reply(200, { explanation: 'I can only explain tax-card notes.', items: [], summary: '', extra: ['I can only explain tax-card notes.'] }) });
	p.button.click();
	await settle(p);
	check('answers without list items fall back to plain text', p.result.querySelector('.avitar-explainer__plain').textContent === 'I can only explain tax-card notes.');

	console.log('\n' + checks + ' checks, ' + failures + ' failures');
	process.exit(failures ? 1 : 0);
}

main().catch((error) => {
	console.error(error);
	process.exit(1);
});
