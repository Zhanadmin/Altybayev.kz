// ALT-114: тесты общего «исправителя раскладки» (public/assets/js/layout-fix.js)
const L = require('../public/assets/js/layout-fix.js');
let bad = 0, n = 0;
function eq(name, got, exp) {
  n++;
  if (JSON.stringify(got) !== JSON.stringify(exp)) { bad++; console.log('FAIL', name, JSON.stringify(got), '!=', JSON.stringify(exp)); }
}

// латиница → русская раскладка
eq('Жандос', L.convert(';fyljc', 'ru'), 'жандос');
eq('Жандос заглавная', L.convert(':fyljc', 'ru'), 'Жандос');
eq('Нурлан', L.convert('yehkfy', 'ru'), 'нурлан');
eq('пробел и цифры', L.convert('Rfhbvjd 12', 'ru'), 'Каримов 12');
eq('Ё/х/ъ', L.convert('`[]', 'ru'), 'ёхъ');
// обратно: кириллица → латиница
eq('обратно', L.convert('жандос', 'en'), ';fyljc');
eq('обратно заглавные', L.convert('Жандос', 'en'), ':fyljc');
// candidates
eq('candidates lat', L.candidates(';fyljc')[0], 'жандос');
eq('candidates cyr', L.candidates('жандос')[0], ';fyljc');
eq('candidates пустая', L.candidates('  '), []);
eq('candidates цифры', L.candidates('1990'), []);
eq('candidates смешанная', L.candidates('abc жандос'), []);
eq('без дублей', new Set(L.candidates(';fyljc')).size, L.candidates(';fyljc').length);
// fold: казахские буквы, ё, регистр
eq('fold kk', L.fold('ӘҒҚҢӨҰҮҺІ'), 'агкноууху'.slice(0, 5) + 'ууxи'.replace('x', 'х'));
eq('fold kk2', L.fold('Қайрат'), 'кайрат');
eq('fold kk3', L.fold('Әлішер Үсен Ңұр'), 'алишер усен нур');
eq('fold ё', L.fold('Ёлкин'), 'елкин');
// titleCase / hint
eq('titleCase', L.titleCase('жандос алтыбаев'), 'Жандос Алтыбаев');
eq('hint ru', L.hintText('Жандос', 'ru'), 'Может, вы имели в виду: Жандос?');
eq('hint kk', L.hintText('Жандос', 'kk'), 'Сіз мынаны айтқыңыз келді ме: Жандос?');

// pickCandidate (синхронный поиск по списку)
const items = ['Жандос Алтыбаев', 'Қайрат Нұрланов', 'Асель'];
const test = (it, q) => L.fold(it).includes(L.fold(q));
let r = L.pickCandidate(';fyljc', items, test);
eq('pick найден', r.results, ['Жандос Алтыбаев']);
eq('pick hint', r.hint, 'Жандос');
eq('pick used', r.used, 'жандос');
r = L.pickCandidate('жандос', items, test);
eq('pick без подсказки если найдено', [r.results.length, r.hint], [1, '']);
r = L.pickCandidate('rfqhfn', items, test);
eq('pick казахская буква қ', r.results, ['Қайрат Нұрланов']);
r = L.pickCandidate('zzzzqq', items, test);
eq('pick пусто', [r.results.length, r.hint], [0, '']);
r = L.pickCandidate('', items, test);
eq('pick пустой запрос', r.hint, '');

// smartSearch (серверный поиск через fetcher)
const calls = [];
const fetcher = (q) => { calls.push(q); return Promise.resolve(q === 'жандос' ? [{ id: 1 }] : []); };
L.smartSearch(';fyljc', fetcher).then((res) => {
  eq('smart results', res.results, [{ id: 1 }]);
  eq('smart hint', res.hint, 'Жандос');
  eq('smart calls', calls.slice(0, 2), [';fyljc', 'жандос']);
  return L.smartSearch('жандос', fetcher);
}).then((res) => {
  eq('smart прямое', [res.results.length, res.hint], [1, '']);
  return L.smartSearch('', () => Promise.resolve([]));
}).then((res) => {
  eq('smart пустой', [res.results.length, res.hint], [0, '']);
  console.log(bad ? 'FAILED ' + bad + ' из ' + n : 'OK ' + n);
  process.exit(bad ? 1 : 0);
});
