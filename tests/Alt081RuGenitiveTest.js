const g = require('../public/assets/js/ru-genitive.js');
const cases = {
  'Жандос': 'Жандоса', 'Ғабит': 'Ғабита', 'Дастан': 'Дастана', 'Алтыбай': 'Алтыбая',
  'Нұрлан': 'Нұрлана', 'Бауыржан': 'Бауыржана', 'Серік': 'Серіка', 'Абай': 'Абая',
  'Игорь': 'Игоря', 'Илья': 'Ильи', 'Никита': 'Никиты', 'Лука': 'Луки',
  'Амангелді': 'Амангелді', 'Қайрат': 'Қайрата', 'Мұрат': 'Мұрата', 'Ерлан': 'Ерлана'
};
let bad = 0;
for (const [n, e] of Object.entries(cases)) {
  const r = g(n);
  if (r !== e) { bad++; console.log('FAIL', n, r, e); }
}
console.log(bad ? 'FAILED ' + bad : 'OK ' + Object.keys(cases).length);
process.exit(bad ? 1 : 0);
