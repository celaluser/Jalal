// Platform tanıma: aynı sosyal ağın farklı yazımları (süslü/Kiril benzeri harfler, bölünmüş, Azerice/Rusça) aynı platform sayılır.
// Çalıştır: node platform-tests.js
const src = require('fs').readFileSync(require('path').join(__dirname, '../tgwatch/chrome-extension/content.js'), 'utf8');
const a = src.indexOf('  const PLATFORMS = ['), b = src.indexOf('  const urlOf = (el) => {');
const platformOf = new Function(src.slice(a, b) + '; return platformOf;')();
const cases = [
 ['İnstagram Takipçi','instagram'],['𝐈𝐧𝐬𝐭𝐚𝐠𝐫𝐚𝐦 Followers','instagram'],['ɪɴsᴛᴀɢʀᴀᴍ ʟɪᴋᴇs','instagram'],['Instаgram (kiril а)','instagram'],
 ['I.n.s.t.a.g.r.a.m','instagram'],['İnstaqram izləyici','instagram'],['Инстаграм подписчики','instagram'],['IG Likes','instagram'],['Inst@gram','instagram'],
 ['Ｉｎｓｔａｇｒａｍ','instagram'],['Ⓘⓝⓢⓣⓐⓖⓡⓐⓜ','instagram'],['Insta​gram','instagram'],['Instant Views','null'],
 ['Tik Tok','tiktok'],['Tik-Tok Bəyəni','tiktok'],['ТикТок','tiktok'],['𝙏𝙞𝙠𝙏𝙤𝙠','tiktok'],['T i k T o k','tiktok'],
 ['You Tube','youtube'],['YOUTUBE','youtube'],['Ютуб просмотры','youtube'],['Y0uTube','youtube'],['YT Views','youtube'],['Yutub Baxış','youtube'],
 ['Face Book','facebook'],['Feysbuk','facebook'],['Фейсбук','facebook'],['FB Likes','facebook'],
 ['Teleqram Üzv','telegram'],['Телеграм','telegram'],['TG Members','telegram'],
 ['Twitter','twitter'],['X Followers','twitter'],['Twitter/X','twitter'],['Твиттер','twitter'],['X (Twitter)','twitter'],['10x Faster','null'],
 ['WhatsApp','whatsapp'],['Vatsap','whatsapp'],['Ватсап','whatsapp'],
 ['ВК друзья','vk'],['VKontakte','vk'],['Одноклассники','odnoklassniki'],['Spotify Plays','spotify'],['Спотифай','spotify'],
 ['Free Services 1000','null'],['Website Traffic','null'],['Kick Followers','kick'],['Snap Chat','snapchat'],
];
let bad = 0;
for (const [n, w] of cases) { const r = String(platformOf(n)); if (r !== w) { bad++; console.log('✗', n, '→', r, '(beklenen ' + w + ')'); } }
console.log(bad ? '✗ ' + bad + ' hatalı' : '✓ hepsi geçti (' + cases.length + ' yazım)'); process.exit(bad ? 1 : 0);
