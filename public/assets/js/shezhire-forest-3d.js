/**
 * ============================================================================
 * ШЕЖІРЕ-ОРМАН 3D: Фотореалистичный живой лес предков казахского рода Керей
 * WebGL / Three.js r160+ интерактивная 3D-локация от первого лица
 * ============================================================================
 * Проект: altybayev.kz (TASK-006)
 * Архитектура:
 *  1. ShezhireAudioEngine: 3D пространственное аудио (ветер, степные птицы, домбра)
 *  2. ShezhireAtmosphere: Скайбокс степи (рассвет/закат), солнце, God Rays, туман
 *  3. ShezhireTerrain: Холмистая степная поляна с цветами и тропинкой
 *  4. ShezhireTreeGenerator: Процедурный Байтерек Керей и рощи родственных родов
 *  5. ShezhireShaderLeaves: Шейдерный шелест живых потомков и золотое свечение предков
 *  6. ShezhireKinshipThreads: Светящиеся парящие нити родства между древами
 *  7. ShezhireControls: Вид от первого лица (WASD + мышь PointerLock), мобильный джойстик, обзор с высоты (Orbit)
 *  8. ShezhireRaycaster: Интерактивный прицел, подсветка и карточки персон
 *  9. ShezhirePerformance: Адаптивный LOD и контроль 60 FPS
 */

(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define(['three'], factory);
    } else if (typeof exports === 'object') {
        module.exports = factory(require('three'));
    } else {
        root.ShezhireForest3D = factory(root.THREE);
    }
}(typeof self !== 'undefined' ? self : this, function (THREE) {
    'use strict';

    if (!THREE) {
        console.warn('ShezhireForest3D: THREE is not globally defined, will wait or expect ESM.');
    }

    // =========================================================================
    // 1. АУДИО ДВИЖОК: 3D ПРОСТРАНСТВЕННЫЙ ЗВУКОВОЙ ЛАНДШАФТ (Web Audio API)
    // =========================================================================
    class ShezhireAudioEngine {
        constructor() {
            this.ctx = null;
            this.isMuted = false;
            this.masterGain = null;
            this.windGain = null;
            this.birdsGain = null;
            this.dombraGain = null;
            this.birdTimer = null;
            this.dombraTimer = null;
            this.dombraNotes = [146.83, 196.00, 220.00, 261.63, 293.66, 392.00, 440.00]; // Pentatonic Dombra D, G, A, C, D, G, A
            this.isInitialized = false;
        }

        init() {
            if (this.isInitialized) return;
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                console.log('Web Audio API not supported in this browser.');
                return;
            }
            this.ctx = new AudioCtx();
            this.masterGain = this.ctx.createGain();
            this.masterGain.gain.setValueAtTime(0.7, this.ctx.currentTime);
            this.masterGain.connect(this.ctx.destination);

            this.setupSteppeWind();
            this.setupSteppeBirds();
            this.setupDombraMotif();
            this.isInitialized = true;
        }

        resume() {
            if (!this.ctx) this.init();
            if (this.ctx && this.ctx.state === 'suspended') {
                this.ctx.resume();
            }
        }

        toggleMute() {
            if (!this.isInitialized) this.init();
            this.resume();
            this.isMuted = !this.isMuted;
            if (this.masterGain) {
                const targetGain = this.isMuted ? 0 : 0.7;
                this.masterGain.gain.setTargetAtTime(targetGain, this.ctx.currentTime, 0.1);
            }
            return !this.isMuted;
        }

        // Синтез степного ветра сквозь крону (Розовый шум + фильтр + LFO)
        setupSteppeWind() {
            if (!this.ctx) return;
            const bufferSize = this.ctx.sampleRate * 2;
            const noiseBuffer = this.ctx.createBuffer(1, bufferSize, this.ctx.sampleRate);
            const output = noiseBuffer.getChannelData(0);
            let b0 = 0, b1 = 0, b2 = 0, b3 = 0, b4 = 0, b5 = 0, b6 = 0;
            for (let i = 0; i < bufferSize; i++) {
                const white = Math.random() * 2 - 1;
                b0 = 0.99886 * b0 + white * 0.0555179;
                b1 = 0.99332 * b1 + white * 0.0750759;
                b2 = 0.96900 * b2 + white * 0.1538520;
                b3 = 0.86650 * b3 + white * 0.3104856;
                b4 = 0.55000 * b4 + white * 0.5329522;
                b5 = -0.7616 * b5 - white * 0.0168980;
                output[i] = (b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362) * 0.04;
                b6 = white * 0.115926;
            }

            const whiteNoise = this.ctx.createBufferSource();
            whiteNoise.buffer = noiseBuffer;
            whiteNoise.loop = true;

            const filter = this.ctx.createBiquadFilter();
            filter.type = 'bandpass';
            filter.frequency.setValueAtTime(450, this.ctx.currentTime);
            filter.Q.setValueAtTime(1.8, this.ctx.currentTime);

            // Медленные порывы ветра (LFO)
            const lfo = this.ctx.createOscillator();
            lfo.frequency.setValueAtTime(0.12, this.ctx.currentTime);
            const lfoGain = this.ctx.createGain();
            lfoGain.gain.setValueAtTime(250, this.ctx.currentTime);
            lfo.connect(lfoGain);
            lfoGain.connect(filter.frequency);
            lfo.start();

            this.windGain = this.ctx.createGain();
            this.windGain.gain.setValueAtTime(0.35, this.ctx.currentTime);

            whiteNoise.connect(filter);
            filter.connect(this.windGain);
            this.windGain.connect(this.masterGain);
            whiteNoise.start();
        }

        // Пространственное пение степных птиц (FM-синтез трелей жаворонка)
        setupSteppeBirds() {
            if (!this.ctx) return;
            this.birdsGain = this.ctx.createGain();
            this.birdsGain.gain.setValueAtTime(0.25, this.ctx.currentTime);
            this.birdsGain.connect(this.masterGain);

            const triggerChirp = () => {
                if (this.isMuted || !this.ctx || this.ctx.state !== 'running') {
                    this.birdTimer = setTimeout(triggerChirp, 3000 + Math.random() * 4000);
                    return;
                }
                const now = this.ctx.currentTime;
                const osc = this.ctx.createOscillator();
                const gain = this.ctx.createGain();
                const baseFreq = 2600 + Math.random() * 1200;

                osc.type = 'sine';
                osc.frequency.setValueAtTime(baseFreq, now);
                osc.frequency.exponentialRampToValueAtTime(baseFreq + (Math.random() * 800 - 400), now + 0.08);
                osc.frequency.exponentialRampToValueAtTime(baseFreq + 600, now + 0.16);

                gain.gain.setValueAtTime(0.01, now);
                gain.gain.linearRampToValueAtTime(0.18, now + 0.04);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.22);

                osc.connect(gain);
                gain.connect(this.birdsGain);

                osc.start(now);
                osc.stop(now + 0.25);

                const nextInterval = 2500 + Math.random() * 5000;
                this.birdTimer = setTimeout(triggerChirp, nextInterval);
            };

            this.birdTimer = setTimeout(triggerChirp, 2000);
        }

        // Мягкий перебор струн домбры (медитативный казахский кюй на заднем плане)
        setupDombraMotif() {
            if (!this.ctx) return;
            this.dombraGain = this.ctx.createGain();
            this.dombraGain.gain.setValueAtTime(0.18, this.ctx.currentTime);
            this.dombraGain.connect(this.masterGain);

            let noteIdx = 0;
            const playPluck = () => {
                if (this.isMuted || !this.ctx || this.ctx.state !== 'running') {
                    this.dombraTimer = setTimeout(playPluck, 2000);
                    return;
                }
                const now = this.ctx.currentTime;
                const freq = this.dombraNotes[noteIdx % this.dombraNotes.length];
                noteIdx++;

                // Струна (комбинация синусоиды и пилы для деревянного тембра)
                const osc1 = this.ctx.createOscillator();
                const osc2 = this.ctx.createOscillator();
                const gain = this.ctx.createGain();

                osc1.type = 'triangle';
                osc2.type = 'sawtooth';
                osc1.frequency.setValueAtTime(freq, now);
                osc2.frequency.setValueAtTime(freq * 2, now);

                const osc2Gain = this.ctx.createGain();
                osc2Gain.gain.setValueAtTime(0.15, now);
                osc2.connect(osc2Gain);

                gain.gain.setValueAtTime(0.01, now);
                gain.gain.linearRampToValueAtTime(0.2, now + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 1.2);

                osc1.connect(gain);
                osc2Gain.connect(gain);
                gain.connect(this.dombraGain);

                osc1.start(now);
                osc2.start(now);
                osc1.stop(now + 1.3);
                osc2.stop(now + 1.3);

                const nextDelay = 800 + Math.random() * 1200;
                this.dombraTimer = setTimeout(playPluck, nextDelay);
            };

            this.dombraTimer = setTimeout(playPluck, 3000);
        }

        destroy() {
            clearTimeout(this.birdTimer);
            clearTimeout(this.dombraTimer);
            if (this.ctx) {
                this.ctx.close();
                this.ctx = null;
            }
        }
    }

    // =========================================================================
    // 2. АТМОСФЕРА КАЗАХСКОЙ СТЕПИ: СКАЙБОКС, СОЛНЦЕ, GOD RAYS, ТУМАН
    // =========================================================================
    class ShezhireAtmosphere {
        constructor(scene, renderer) {
            this.scene = scene;
            this.renderer = renderer;
            this.sunMesh = null;
            this.dirLight = null;
            this.godRaysGroup = null;
            this.time = 0;
            this.build();
        }

        build() {
            // Мягкий золотистый дневной степной туман (Ясный солнечный день из концепт-арта)
            this.scene.fog = new THREE.FogExp2(0xfef3c7, 0.0028);

            // Купол неба степи с градиентом ясного солнечного дня
            const skyGeo = new THREE.SphereGeometry(700, 32, 24);
            const skyMat = new THREE.ShaderMaterial({
                side: THREE.BackSide,
                uniforms: {
                    topColor: { value: new THREE.Color(0x0284c7) }, // лазурный зенит ясного дня
                    midColor: { value: new THREE.Color(0x38bdf8) }, // нежно-голубой степной свод
                    bottomColor: { value: new THREE.Color(0xfef08a) }, // золотистый солнечный горизонт
                    hazeColor: { value: new THREE.Color(0xfffbeb) }, // солнечная дымка
                    offset: { value: 30 },
                    exponent: { value: 0.5 }
                },
                vertexShader: `
                    varying vec3 vWorldPosition;
                    void main() {
                        vec4 worldPosition = modelMatrix * vec4(position, 1.0);
                        vWorldPosition = worldPosition.xyz;
                        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                    }
                `,
                fragmentShader: `
                    uniform vec3 topColor;
                    uniform vec3 midColor;
                    uniform vec3 bottomColor;
                    uniform vec3 hazeColor;
                    uniform float offset;
                    uniform float exponent;
                    varying vec3 vWorldPosition;
                    void main() {
                        float h = normalize(vWorldPosition + offset).y;
                        vec3 sky = mix(bottomColor, midColor, max(pow(max(h, 0.0), exponent), 0.0));
                        if (h > 0.4) {
                            sky = mix(sky, topColor, (h - 0.4) / 0.6);
                        } else if (h < 0.05) {
                            sky = mix(hazeColor, sky, max(h / 0.05, 0.0));
                        }
                        gl_FragColor = vec4(sky, 1.0);
                    }
                `
            });
            const skyMesh = new THREE.Mesh(skyGeo, skyMat);
            this.scene.add(skyMesh);

            // Освещение: Направленное солнце степи + мягкие тени PCFSoft
            this.dirLight = new THREE.DirectionalLight(0xfff1b8, 2.4);
            this.dirLight.position.set(-60, 95, -110);
            this.dirLight.castShadow = true;
            this.dirLight.shadow.mapSize.width = 2048;
            this.dirLight.shadow.mapSize.height = 2048;
            this.dirLight.shadow.camera.near = 0.5;
            this.dirLight.shadow.camera.far = 400;
            const d = 90;
            this.dirLight.shadow.camera.left = -d;
            this.dirLight.shadow.camera.right = d;
            this.dirLight.shadow.camera.top = d;
            this.dirLight.shadow.camera.bottom = -d;
            this.dirLight.shadow.bias = -0.0004;
            this.scene.add(this.dirLight);

            // Рассеянный свет степного свода
            const hemiLight = new THREE.HemisphereLight(0x93c5fd, 0x166534, 0.85);
            this.scene.add(hemiLight);

            // Диск священного солнца степи (Күн шапағаты)
            const sunGeo = new THREE.SphereGeometry(18, 24, 24);
            const sunMat = new THREE.MeshBasicMaterial({
                color: 0xfffae0,
                transparent: true,
                opacity: 0.95
            });
            this.sunMesh = new THREE.Mesh(sunGeo, sunMat);
            this.sunMesh.position.copy(this.dirLight.position).multiplyScalar(3.0);
            this.scene.add(this.sunMesh);

            // Солнечное гало / лучи сквозь крону (Volumetric God Rays)
            this.godRaysGroup = new THREE.Group();
            const rayGeo = new THREE.ConeGeometry(35, 180, 16, 1, true);
            const rayMat = new THREE.ShaderMaterial({
                transparent: true,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
                side: THREE.DoubleSide,
                uniforms: {
                    uTime: { value: 0 },
                    color: { value: new THREE.Color(0xfde047) }
                },
                vertexShader: `
                    varying vec2 vUv;
                    varying vec3 vNormal;
                    void main() {
                        vUv = uv;
                        vNormal = normal;
                        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
                    }
                `,
                fragmentShader: `
                    uniform float uTime;
                    uniform vec3 color;
                    varying vec2 vUv;
                    void main() {
                        float pulse = 0.5 + 0.5 * sin(uTime * 1.5 + vUv.y * 3.0);
                        float alpha = (1.0 - vUv.y) * 0.14 * pulse;
                        gl_FragColor = vec4(color, alpha);
                    }
                `
            });

            for (let i = 0; i < 5; i++) {
                const ray = new THREE.Mesh(rayGeo, rayMat);
                ray.rotation.x = Math.PI / 2 + 0.3;
                ray.rotation.z = (i * Math.PI) / 2.5 + 0.2;
                ray.position.set(-20 + i * 8, 45, -40 + i * 5);
                this.godRaysGroup.add(ray);
            }
            this.scene.add(this.godRaysGroup);
        }

        update(delta) {
            this.time += delta;
            if (this.godRaysGroup) {
                this.godRaysGroup.children.forEach((ray) => {
                    if (ray.material.uniforms && ray.material.uniforms.uTime) {
                        ray.material.uniforms.uTime.value = this.time;
                    }
                });
            }
        }
    }

    // =========================================================================
    // 3. СТЕПНОЙ ЛАНДШАФТ: ШӨПТІ АЛАҢҚАЙ, ТЮЛЬПАНЫ И ТРОПИНКА
    // =========================================================================
    class ShezhireTerrain {
        constructor(scene) {
            this.scene = scene;
            this.terrainMesh = null;
            this.build();
        }

        build() {
            const size = 320;
            const segments = 64;
            const geo = new THREE.PlaneGeometry(size, size, segments, segments);
            geo.rotateX(-Math.PI / 2);

            // Процедурные волны казахской степи
            const pos = geo.attributes.position;
            for (let i = 0; i < pos.count; i++) {
                const x = pos.getX(i);
                const z = pos.getZ(i);
                const distFromCenter = Math.sqrt(x * x + z * z);
                // Центральная поляна у священного древа более ровная, по краям холмы
                const hillFactor = Math.min(distFromCenter / 70, 1.0);
                const height = (Math.sin(x * 0.04) * 2.5 + Math.cos(z * 0.04) * 2.5 + Math.sin(x * 0.08 + z * 0.06) * 1.5) * hillFactor;
                pos.setY(i, height);
            }
            geo.computeVertexNormals();

            // Процедурная текстура степной травы и тропы
            const canvas = document.createElement('canvas');
            canvas.width = 512;
            canvas.height = 512;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#166534'; // Сочный изумрудный цвет шөпті алаңқай
            ctx.fillRect(0, 0, 512, 512);

            // Крапинки и травинки
            for (let i = 0; i < 4000; i++) {
                ctx.fillStyle = Math.random() > 0.5 ? '#15803d' : '#14532d';
                ctx.fillRect(Math.random() * 512, Math.random() * 512, 2, 4);
            }
            // Золотистые пятна степного ковыля
            for (let i = 0; i < 600; i++) {
                ctx.fillStyle = 'rgba(217, 119, 6, 0.25)';
                ctx.fillRect(Math.random() * 512, Math.random() * 512, 4, 4);
            }

            const texture = new THREE.CanvasTexture(canvas);
            texture.wrapS = THREE.RepeatWrapping;
            texture.wrapT = THREE.RepeatWrapping;
            texture.repeat.set(24, 24);

            const mat = new THREE.MeshStandardMaterial({
                map: texture,
                roughness: 0.9,
                metalness: 0.05,
                color: 0x22c55e
            });

            this.terrainMesh = new THREE.Mesh(geo, mat);
            this.terrainMesh.receiveShadow = true;
            this.scene.add(this.terrainMesh);

            // Инстансинг степных цветов (қызыл қызғалдақтар / красные тюльпаны и сары бәйшешектер)
            this.buildWildflowers();
        }

        buildWildflowers() {
            const count = 1200;
            const flowerGeo = new THREE.ConeGeometry(0.2, 0.45, 5);
            const flowerMat = new THREE.MeshStandardMaterial({
                roughness: 0.6,
                metalness: 0.1
            });

            const instMesh = new THREE.InstancedMesh(flowerGeo, flowerMat, count);
            const dummy = new THREE.Object3D();
            const colors = [new THREE.Color(0xef4444), new THREE.Color(0xfacc15), new THREE.Color(0xec4899), new THREE.Color(0xffffff)];

            let added = 0;
            for (let i = 0; i < count; i++) {
                const angle = Math.random() * Math.PI * 2;
                const dist = 6 + Math.random() * 65; // вокруг деревьев
                const x = Math.cos(angle) * dist;
                const z = Math.sin(angle) * dist;
                const y = 0.2;

                dummy.position.set(x, y, z);
                dummy.rotation.x = (Math.random() - 0.5) * 0.3;
                dummy.rotation.z = (Math.random() - 0.5) * 0.3;
                dummy.rotation.y = Math.random() * Math.PI * 2;
                dummy.scale.setScalar(0.7 + Math.random() * 0.8);
                dummy.updateMatrix();

                instMesh.setMatrixAt(added, dummy.matrix);
                instMesh.setColorAt(added, colors[Math.floor(Math.random() * colors.length)]);
                added++;
            }
            instMesh.instanceMatrix.needsUpdate = true;
            if (instMesh.instanceColor) instMesh.instanceColor.needsUpdate = true;
            instMesh.receiveShadow = true;
            this.scene.add(instMesh);
        }

        getHeightAt(x, z) {
            const dist = Math.sqrt(x * x + z * z);
            const hillFactor = Math.min(dist / 70, 1.0);
            return (Math.sin(x * 0.04) * 2.5 + Math.cos(z * 0.04) * 2.5 + Math.sin(x * 0.08 + z * 0.06) * 1.5) * hillFactor;
        }
    }

    // =========================================================================
    // 4. ПРОЦЕДУРНЫЙ БАЙТЕРЕК КЕРЕЙ И РОЩИ РОДСТВЕННЫХ РОДОВ
    // =========================================================================
    class ShezhireTreeGenerator {
        constructor(scene, treeData, kinshipService) {
            this.scene = scene;
            this.treeData = treeData || { persons: [], marriages: [], clans: [] };
            this.kinshipService = kinshipService;
            this.interactiveNodes = []; // Массив интерактивных объектов для Raycasting
            this.leavesMeshLiving = null;
            this.leavesMeshAncestors = null;
            this.flowersMesh = null;
            this.leafDummy = new THREE.Object3D();
            this.time = 0;
            this.grovePositions = new Map();
        }

        buildForest() {
            const persons = this.treeData.persons || [];
            const marriages = this.treeData.marriages || [];
            const personMap = new Map();
            persons.forEach(p => personMap.set(p.id, p));

            // Фокальная персона: Жандос Алтыбаев (@I6@)
            let focal = persons.find(p => p.gedcom_id === '@I6@' || (p.first_name && p.first_name.includes('Жандос'))) || persons[0];

            // 1. ЦЕНТРАЛЬНЫЙ СВЯЩЕННЫЙ БАЙТЕРЕК (РОД КЕРЕЙ / АЛТЫБАЕВТАР)
            this.buildSacredBaitemek(focal, personMap, marriages);

            // 2. СОСЕДНИЕ РОЩИ РОДСТВЕННЫХ РОДОВ (Жағалбайлы, Қоңырат, Арғын, Найман, Дулат)
            this.buildClanGroves();

            // 3. ПАРЯЩИЕ СВЕТОВЫЕ НИТИ РОДСТВА МЕЖДУ ДРЕВАМИ
            this.buildKinshipRibbons();
        }

        buildSacredBaitemek(focal, personMap, marriages) {
            const treeGroup = new THREE.Group();
            treeGroup.position.set(0, 0, 0);

            // Процедурный мощный ствол байтерека
            const trunkMat = this.createBarkMaterial();
            const trunkGeo = new THREE.CylinderGeometry(2.4, 4.2, 18, 16, 8);
            // Добавим органическую кривизну
            const pos = trunkGeo.attributes.position;
            for (let i = 0; i < pos.count; i++) {
                const y = pos.getY(i);
                const angle = Math.atan2(pos.getZ(i), pos.getX(i));
                const radiusDistort = Math.sin(y * 0.8 + angle * 4.0) * 0.35;
                pos.setX(i, pos.getX(i) * (1 + radiusDistort * 0.2));
                pos.setZ(i, pos.getZ(i) * (1 + radiusDistort * 0.2));
            }
            trunkGeo.computeVertexNormals();

            const trunkMesh = new THREE.Mesh(trunkGeo, trunkMat);
            trunkMesh.position.y = 9;
            trunkMesh.castShadow = true;
            trunkMesh.receiveShadow = true;
            treeGroup.add(trunkMesh);

            // Корни, уходящие в землю степи
            for (let i = 0; i < 7; i++) {
                const angle = (i / 7) * Math.PI * 2;
                const rootGeo = new THREE.CylinderGeometry(0.8, 1.8, 8, 8);
                const rootMesh = new THREE.Mesh(rootGeo, trunkMat);
                rootMesh.position.set(Math.cos(angle) * 3.5, 1.5, Math.sin(angle) * 3.5);
                rootMesh.rotation.z = Math.cos(angle) * 0.45;
                rootMesh.rotation.x = -Math.sin(angle) * 0.45;
                rootMesh.castShadow = true;
                treeGroup.add(rootMesh);
            }

            // Киелі алтын тамыр сәулесі (Золотистое свечение между корнями из концепт-арта)
            const rootGlowMat = new THREE.MeshStandardMaterial({
                color: 0xf59e0b,
                emissive: 0xd97706,
                emissiveIntensity: 0.95,
                roughness: 0.3,
                metalness: 0.1
            });
            for (let i = 0; i < 7; i++) {
                const angle = ((i + 0.5) / 7) * Math.PI * 2;
                const seamGeo = new THREE.CylinderGeometry(0.25, 0.7, 6, 6);
                const seamMesh = new THREE.Mesh(seamGeo, rootGlowMat);
                seamMesh.position.set(Math.cos(angle) * 3.0, 0.4, Math.sin(angle) * 3.0);
                seamMesh.rotation.z = Math.cos(angle) * 0.4;
                seamMesh.rotation.x = -Math.sin(angle) * 0.4;
                treeGroup.add(seamMesh);
            }

            // Массивные ветви поколений (Ата-баба бұтақтары)
            const branchTiers = [
                { y: 14, count: 5, radius: 8, length: 12, angleOffset: 0 },
                { y: 17, count: 6, radius: 10, length: 14, angleOffset: 0.5 },
                { y: 20, count: 6, radius: 12, length: 16, angleOffset: 1.0 },
                { y: 23, count: 5, radius: 9, length: 11, angleOffset: 1.5 }
            ];

            branchTiers.forEach((tier) => {
                for (let b = 0; b < tier.count; b++) {
                    const angle = (b / tier.count) * Math.PI * 2 + tier.angleOffset;
                    const branchGeo = new THREE.CylinderGeometry(0.4, 1.2, tier.length, 8);
                    const branchMesh = new THREE.Mesh(branchGeo, trunkMat);
                    branchMesh.position.set(Math.cos(angle) * 3, tier.y, Math.sin(angle) * 3);
                    branchMesh.rotation.z = Math.cos(angle) * 0.85;
                    branchMesh.rotation.x = -Math.sin(angle) * 0.85;
                    branchMesh.castShadow = true;
                    treeGroup.add(branchMesh);
                }
            });

            // Тікелей ата-баба діңгегі (Ствол от Керея через Самай би, Алтыбай баба до Жандоса)
            const directAncestors = [];
            let cur = focal;
            const seen = new Set();
            while (cur && !seen.has(cur.id)) {
                seen.add(cur.id);
                directAncestors.push(cur);
                cur = cur.father_id ? personMap.get(cur.father_id) : null;
            }

            const kereyPerson = (this.treeData.persons || []).find(p => 
                (p.first_name && p.first_name.includes('Керей')) || (p.last_name && p.last_name.includes('Керей'))
            );
            if (kereyPerson && !seen.has(kereyPerson.id)) {
                directAncestors.push(kereyPerson);
            }
            directAncestors.reverse(); // От Керея к Жандосу снизу вверх

            directAncestors.forEach((anc, aIdx) => {
                const heightY = 3.6 + aIdx * 2.3;
                if (heightY > 17.5) return;

                const isRoot = (aIdx === 0);
                const isAltybay = (anc.first_name && anc.first_name.includes('Алтыбай')) || (anc.last_name && anc.last_name.includes('Алтыбай'));

                const medGeo = new THREE.CylinderGeometry(isRoot ? 0.75 : 0.58, isRoot ? 0.75 : 0.58, 0.14, 16);
                medGeo.rotateX(Math.PI / 2);

                const medMat = new THREE.MeshStandardMaterial({
                    color: isRoot ? 0xf59e0b : (isAltybay ? 0xd97706 : 0xb45309),
                    emissive: isRoot ? 0xd97706 : 0x78350f,
                    emissiveIntensity: 0.85,
                    roughness: 0.3,
                    metalness: 0.7
                });

                const medallion = new THREE.Mesh(medGeo, medMat);
                medallion.position.set(0, heightY, 2.6);
                treeGroup.add(medallion);

                this.interactiveNodes.push({
                    object: medallion,
                    type: 'person',
                    data: anc
                });
            });

            // Мемориалдық тас / Тотем рода Керей (Алтыбаевтар)
            const totemGeo = new THREE.BoxGeometry(2.4, 3.2, 0.45);
            const totemMat = new THREE.MeshStandardMaterial({
                color: 0xb45309,
                emissive: 0x78350f,
                emissiveIntensity: 0.45,
                roughness: 0.6,
                metalness: 0.3
            });
            const totemMesh = new THREE.Mesh(totemGeo, totemMat);
            totemMesh.position.set(0, 2.0, 4.6);
            totemMesh.castShadow = true;
            treeGroup.add(totemMesh);

            // Интерактивная табличка рода
            this.interactiveNodes.push({
                object: totemMesh,
                type: 'clan_totem',
                data: {
                    name_kk: 'Керей баба — Алтыбаевтар әулетінің қасиетті Бәйтерегі (Түпкі тамыр)',
                    name_ru: 'Керей баба — Священный Байтерек династии Алтыбаевых (Корень шежире)',
                    clan: 'Керей (Ашамайлы / Абақ)',
                    desc: 'Шежіренің қасиетті тамыры. Таңба: Ашамай, Ұран: Ошыбай! Барлық ата-бабалар мен ұрпақтар рухының киелі мекені.'
                }
            });

            this.scene.add(treeGroup);
            this.grovePositions.set('Керей', new THREE.Vector3(0, 0, 0));

            // Построение кроны и распределение персон (листья, цветы, золотые свитки)
            this.buildFoliageAndPersons(treeGroup, focal, personMap);
        }

        createBarkMaterial() {
            const canvas = document.createElement('canvas');
            canvas.width = 512;
            canvas.height = 512;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#3f2212'; // Темно-коричневая кора дуба
            ctx.fillRect(0, 0, 512, 512);

            // Продольные бороздки коры
            for (let i = 0; i < 500; i++) {
                ctx.fillStyle = i % 2 === 0 ? '#26150b' : '#57331c';
                const x = Math.random() * 512;
                const w = 4 + Math.random() * 8;
                ctx.fillRect(x, 0, w, 512);
            }

            const texture = new THREE.CanvasTexture(canvas);
            texture.wrapS = THREE.RepeatWrapping;
            texture.wrapT = THREE.RepeatWrapping;
            texture.repeat.set(2, 6);

            return new THREE.MeshStandardMaterial({
                map: texture,
                roughness: 0.85,
                metalness: 0.1
            });
        }

        buildFoliageAndPersons(treeGroup, focal, personMap) {
            const persons = this.treeData.persons || [];
            const leafGeo = new THREE.PlaneGeometry(0.9, 1.4);
            leafGeo.rotateY(Math.PI / 4);

            // 1. Листья живых потомков (Свежий изумрудный цвет + колыхание на ветру)
            const livingMat = new THREE.MeshStandardMaterial({
                color: 0x22c55e,
                roughness: 0.5,
                metalness: 0.1,
                side: THREE.DoubleSide
            });

            // 2. Листья и свитки ушедших предков (Теплое янтарно-золотое свечение памяти)
            const ancestorMat = new THREE.MeshStandardMaterial({
                color: 0xd97706,
                emissive: 0xb45309,
                emissiveIntensity: 0.65,
                roughness: 0.4,
                metalness: 0.4,
                side: THREE.DoubleSide
            });

            // 3. Цветы арулар (дочери и снохи)
            const flowerMat = new THREE.MeshStandardMaterial({
                color: 0xf472b6,
                emissive: 0xec4899,
                emissiveIntensity: 0.4,
                roughness: 0.5,
                side: THREE.DoubleSide
            });

            const livingPersons = persons.filter(p => !p.is_deceased && p.gender !== 'F');
            const ancestorPersons = persons.filter(p => p.is_deceased);
            const femalePersons = persons.filter(p => p.gender === 'F');

            // Создаем инстансы
            const totalLeafCount = 2800;
            const instLiving = new THREE.InstancedMesh(leafGeo, livingMat, Math.max(livingPersons.length * 8, 800));
            const instAncestors = new THREE.InstancedMesh(leafGeo, ancestorMat, Math.max(ancestorPersons.length * 8, 800));
            const instFlowers = new THREE.InstancedMesh(leafGeo, flowerMat, Math.max(femalePersons.length * 8, 600));

            let idxLiv = 0, idxAnc = 0, idxFlw = 0;
            const dummy = this.leafDummy;

            // Распределяем крону куполом над ветвями в стиле концепт-арта (shezhire_living_forest_concept.jpg)
            for (let i = 0; i < totalLeafCount; i++) {
                const u = Math.random();
                const v = Math.random();
                const theta = u * 2.0 * Math.PI;
                const phi = Math.acos(2.0 * v - 1.0);
                const r = 8 + Math.random() * 12;

                const x = r * Math.sin(phi) * Math.cos(theta);
                const y = 16 + Math.abs(r * Math.cos(phi)) * 0.95;
                const z = r * Math.sin(phi) * Math.sin(theta);

                dummy.position.set(x, y, z);
                dummy.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, Math.random() * Math.PI);
                dummy.scale.setScalar(0.7 + Math.random() * 0.6);
                dummy.updateMatrix();

                // В соответствии с арт-эскизом:
                // Слева (x < -1.5) — алтын жапырақтар ушедших предков (золотое свечение памяти)
                // Справа (x > 1.5) — жасыл жапырақтар живых потомков (свежая зелень жизни)
                // По всей кроне — цветки арулар (дочери и снохи)
                if (x < -1.5 && idxAnc < instAncestors.count) {
                    instAncestors.setMatrixAt(idxAnc++, dummy.matrix);
                } else if (Math.random() < 0.22 && idxFlw < instFlowers.count) {
                    instFlowers.setMatrixAt(idxFlw++, dummy.matrix);
                } else if (idxLiv < instLiving.count) {
                    instLiving.setMatrixAt(idxLiv++, dummy.matrix);
                }
            }

            instLiving.instanceMatrix.needsUpdate = true;
            instAncestors.instanceMatrix.needsUpdate = true;
            instFlowers.instanceMatrix.needsUpdate = true;

            instLiving.castShadow = true;
            instAncestors.castShadow = true;
            instFlowers.castShadow = true;

            treeGroup.add(instLiving);
            treeGroup.add(instAncestors);
            treeGroup.add(instFlowers);
            this.leavesMeshLiving = instLiving;
            this.leavesMeshAncestors = instAncestors;

            // Размещаем осязаемые золотые свитки/сферы предков с именами для кликабельности (Raycasting)
            this.buildInteractivePersonBadges(treeGroup, persons);
        }

        buildInteractivePersonBadges(treeGroup, persons) {
            const badgeGeo = new THREE.CylinderGeometry(0.38, 0.38, 0.09, 16);
            badgeGeo.rotateX(Math.PI / 2);

            const flowerGeo = new THREE.ConeGeometry(0.32, 0.52, 6);
            flowerGeo.rotateX(Math.PI / 2);

            // Размещаем ВСЕХ персон из базы данных (до 270)
            persons.forEach((person, idx) => {
                const isDeceased = !!person.is_deceased;
                const isFemale = (person.gender === 'F');

                let badgeColor = isDeceased ? 0xf59e0b : (isFemale ? 0xf472b6 : 0x22c55e);
                let emissiveColor = isDeceased ? 0xd97706 : (isFemale ? 0xec4899 : 0x15803d);

                const mat = new THREE.MeshStandardMaterial({
                    color: badgeColor,
                    emissive: emissiveColor,
                    emissiveIntensity: isDeceased ? 0.75 : 0.45,
                    roughness: 0.35,
                    metalness: isDeceased ? 0.7 : 0.2
                });

                const mesh = new THREE.Mesh(isFemale ? flowerGeo : badgeGeo, mat);

                // Распределение в пространстве кроны:
                // Ушедшие предки тяготеют к левой золотистой стороне (x < 0, как в арт-эскизе),
                // Живые потомки тяготеют к правой изумрудной стороне (x > 0)
                let theta;
                if (isDeceased) {
                    theta = Math.PI * 0.75 + (Math.sin(idx * 1.7) * 0.95); // левая/осенняя золотистая сторона памяти
                } else {
                    theta = -Math.PI * 0.25 + (Math.sin(idx * 1.7) * 0.95); // правая/весенняя сторона живых
                }

                const radius = 5.5 + (idx % 8) * 1.8;
                const height = 13.0 + (idx % 10) * 1.35;

                const x = Math.cos(theta) * radius;
                const z = Math.sin(theta) * radius;

                mesh.position.set(x, height, z);
                mesh.rotation.y = theta;

                treeGroup.add(mesh);

                // Добавляем в интерактивный пул
                this.interactiveNodes.push({
                    object: mesh,
                    type: 'person',
                    data: person
                });
            });
        }

        // 2. СОСЕДНИЕ РОЩИ РОДСТВЕННЫХ РОДОВ (Жағалбайлы, Қоңырат, Арғын, Найман, Дулат)
        buildClanGroves() {
            const clans = [
                { name_kk: 'Жағалбайлы', name_ru: 'Жагалбайлы (ру анамыз)', angle: 0.6, dist: 42, color: 0x10b981 },
                { name_kk: 'Қоңырат', name_ru: 'Конырат (құдалар)', angle: 1.8, dist: 48, color: 0x3b82f6 },
                { name_kk: 'Арғын', name_ru: 'Аргын (нағашы жұрты)', angle: 3.1, dist: 45, color: 0x8b5cf6 },
                { name_kk: 'Найман', name_ru: 'Найман (сватовской род)', angle: 4.4, dist: 50, color: 0xf59e0b },
                { name_kk: 'Дулат', name_ru: 'Дулат (қайын жұрты)', angle: 5.4, dist: 40, color: 0xec4899 }
            ];

            const barkMat = this.createBarkMaterial();
            const leafGeo = new THREE.SphereGeometry(6, 12, 10);

            clans.forEach((clan) => {
                const group = new THREE.Group();
                const x = Math.cos(clan.angle) * clan.dist;
                const z = Math.sin(clan.angle) * clan.dist;
                group.position.set(x, 0, z);

                // Ствол
                const trunkGeo = new THREE.CylinderGeometry(0.9, 1.6, 12, 10);
                const trunk = new THREE.Mesh(trunkGeo, barkMat);
                trunk.position.y = 6;
                trunk.castShadow = true;
                group.add(trunk);

                // Крона
                const leafMat = new THREE.MeshStandardMaterial({
                    color: clan.color,
                    roughness: 0.6,
                    metalness: 0.1
                });
                const crown = new THREE.Mesh(leafGeo, leafMat);
                crown.position.y = 12;
                crown.scale.set(1.2, 0.9, 1.2);
                crown.castShadow = true;
                group.add(crown);

                // Таңбалы штандарт / ту рода
                const bannerGeo = new THREE.BoxGeometry(1.6, 2.4, 0.2);
                const bannerMat = new THREE.MeshStandardMaterial({
                    color: 0x1e293b,
                    emissive: clan.color,
                    emissiveIntensity: 0.45
                });
                const banner = new THREE.Mesh(bannerGeo, bannerMat);
                banner.position.set(0, 3, 2.2);
                group.add(banner);

                this.interactiveNodes.push({
                    object: banner,
                    type: 'clan_grove',
                    data: {
                        name_kk: `${clan.name_kk} руының қасиетті тоғайы`,
                        name_ru: `Священная роща рода ${clan.name_ru}`,
                        clan: clan.name_kk,
                        desc: 'Аналар мен құда-жекжаттардың мәңгілік ұясы. Шежірелік байланыс нұры арқылы біріккен.'
                    }
                });

                this.scene.add(group);
                this.grovePositions.set(clan.name_kk, new THREE.Vector3(x, 0, z));
            });
        }

        // 3. ПАРЯЩИЕ СВЕТОВЫЕ НИТИ РОДСТВА МЕЖДУ ДРЕВАМИ
        buildKinshipRibbons() {
            const kereyPos = this.grovePositions.get('Керей') || new THREE.Vector3(0, 10, 0);

            this.grovePositions.forEach((pos, clanName) => {
                if (clanName === 'Керей') return;

                // Кривая безье для световой нити родства
                const midX = (kereyPos.x + pos.x) * 0.5;
                const midZ = (kereyPos.z + pos.z) * 0.5;
                const midY = 16 + Math.random() * 4; // Парящая в небе арка

                const curve = new THREE.CatmullRomCurve3([
                    new THREE.Vector3(0, 12, 0),
                    new THREE.Vector3(midX, midY, midZ),
                    new THREE.Vector3(pos.x, 8, pos.z)
                ]);

                const tubeGeo = new THREE.TubeGeometry(curve, 24, 0.12, 6, false);
                const tubeMat = new THREE.MeshBasicMaterial({
                    color: 0xfef08a,
                    transparent: true,
                    opacity: 0.65,
                    blending: THREE.AdditiveBlending
                });

                const ribbon = new THREE.Mesh(tubeGeo, tubeMat);
                this.scene.add(ribbon);
            });
        }

        update(delta) {
            this.time += delta;
            // Колыхание ветвей и листвы на ветру
            if (this.leavesMeshLiving) {
                const sway = Math.sin(this.time * 2.2) * 0.05;
                this.leavesMeshLiving.rotation.y = sway;
            }
            if (this.leavesMeshAncestors) {
                const pulse = 0.5 + 0.5 * Math.sin(this.time * 1.8);
                this.leavesMeshAncestors.material.emissiveIntensity = 0.45 + pulse * 0.4;
            }
        }
    }

    // =========================================================================
    // 5. УПРАВЛЕНИЕ: ПЕРВЫЙ ЛИЦО (WASD + МЫШЬ POINTERLOCK), ДЖОЙСТИК, ОРБИТА
    // =========================================================================
    class ShezhireControls {
        constructor(camera, domElement, terrain) {
            this.camera = camera;
            this.domElement = domElement;
            this.terrain = terrain;

            this.mode = 'fps'; // 'fps' (первое лицо) немесе 'orbit' (құс көзімен шолу)
            this.isLocked = false;

            // Положение и скорость персонажа
            this.velocity = new THREE.Vector3();
            this.direction = new THREE.Vector3();
            this.playerHeight = 1.75; // Рост человека (1.75м)
            this.walkSpeed = 16.0;
            this.runMultiplier = 1.8;
            this.isSprinting = false;
            this.walkCycle = 0;

            // Клавиатура
            this.keys = { forward: false, backward: false, left: false, right: false, jump: false };

            // Поворот камеры от мыши
            this.pitch = 0;
            this.yaw = 0;

            // Орбитальные параметры
            this.orbitTarget = new THREE.Vector3(0, 10, 0);
            this.orbitDistance = 65;
            this.orbitTheta = 0;
            this.orbitPhi = Math.PI / 4;
            this.isOrbitDragging = false;
            this.previousMousePosition = { x: 0, y: 0 };

            // Мобильные сенсоры
            this.touchJoystick = { active: false, startX: 0, startY: 0, moveX: 0, moveY: 0 };
            this.touchLook = { active: false, lastX: 0, lastY: 0 };

            this.initListeners();
            this.setInitialSpawn();
        }

        setInitialSpawn() {
            // Спавн перед Байтереком на поляне (z = 24)
            this.camera.position.set(0, 2.5, 26);
            this.yaw = Math.PI; // взгляд на байтерек в центр (0,0,0)
            this.pitch = 0.15;
            this.updateCameraRotation();
        }

        initListeners() {
            // Клавиатура
            window.addEventListener('keydown', (e) => this.onKeyDown(e));
            window.addEventListener('keyup', (e) => this.onKeyUp(e));

            // Захват курсора (PointerLock)
            this.domElement.addEventListener('click', () => {
                if (this.mode === 'fps' && !this.isLocked) {
                    this.requestPointerLock();
                }
            });

            document.addEventListener('pointerlockchange', () => {
                this.isLocked = document.pointerLockElement === this.domElement;
            });

            // Движение мыши
            window.addEventListener('mousemove', (e) => this.onMouseMove(e));

            // Орбитальное масштабирование колесиком
            this.domElement.addEventListener('wheel', (e) => {
                if (this.mode === 'orbit') {
                    this.orbitDistance = THREE.MathUtils.clamp(this.orbitDistance + e.deltaY * 0.06, 12, 140);
                    e.preventDefault();
                }
            }, { passive: false });

            // Сенсорное управление для смартфонов
            this.initTouchListeners();
        }

        requestPointerLock() {
            try {
                this.domElement.requestPointerLock();
            } catch (err) {
                console.log('PointerLock error:', err);
            }
        }

        exitPointerLock() {
            if (document.exitPointerLock) {
                document.exitPointerLock();
            }
        }

        onKeyDown(e) {
            switch (e.code) {
                case 'KeyW': case 'ArrowUp': this.keys.forward = true; break;
                case 'KeyS': case 'ArrowDown': this.keys.backward = true; break;
                case 'KeyA': case 'ArrowLeft': this.keys.left = true; break;
                case 'KeyD': case 'ArrowRight': this.keys.right = true; break;
                case 'ShiftLeft': case 'ShiftRight': this.isSprinting = true; break;
            }
        }

        onKeyUp(e) {
            switch (e.code) {
                case 'KeyW': case 'ArrowUp': this.keys.forward = false; break;
                case 'KeyS': case 'ArrowDown': this.keys.backward = false; break;
                case 'KeyA': case 'ArrowLeft': this.keys.left = false; break;
                case 'KeyD': case 'ArrowRight': this.keys.right = false; break;
                case 'ShiftLeft': case 'ShiftRight': this.isSprinting = false; break;
            }
        }

        onMouseMove(e) {
            if (this.mode === 'fps') {
                if (!this.isLocked) return;
                const sensitivity = 0.0022;
                this.yaw -= e.movementX * sensitivity;
                this.pitch -= e.movementY * sensitivity;
                this.pitch = Math.max(-Math.PI / 2.2, Math.min(Math.PI / 2.2, this.pitch));
                this.updateCameraRotation();
            } else if (this.mode === 'orbit' && this.isOrbitDragging) {
                const deltaX = e.clientX - this.previousMousePosition.x;
                const deltaY = e.clientY - this.previousMousePosition.y;
                this.orbitTheta -= deltaX * 0.006;
                this.orbitPhi = THREE.MathUtils.clamp(this.orbitPhi - deltaY * 0.006, 0.1, Math.PI / 2.1);
                this.previousMousePosition = { x: e.clientX, y: e.clientY };
            }
        }

        updateCameraRotation() {
            const euler = new THREE.Euler(0, 0, 0, 'YXZ');
            euler.x = this.pitch;
            euler.y = this.yaw;
            this.camera.quaternion.setFromEuler(euler);
        }

        initTouchListeners() {
            const el = this.domElement;
            el.addEventListener('touchstart', (e) => {
                for (let i = 0; i < e.changedTouches.length; i++) {
                    const touch = e.changedTouches[i];
                    // Левая половина экрана — джойстик ходьбы
                    if (touch.clientX < window.innerWidth * 0.45) {
                        this.touchJoystick.active = true;
                        this.touchJoystick.startX = touch.clientX;
                        this.touchJoystick.startY = touch.clientY;
                        this.touchJoystick.moveX = 0;
                        this.touchJoystick.moveY = 0;
                    } else {
                        // Правая половина — вращение взгляда
                        this.touchLook.active = true;
                        this.touchLook.lastX = touch.clientX;
                        this.touchLook.lastY = touch.clientY;
                    }
                }
            }, { passive: true });

            el.addEventListener('touchmove', (e) => {
                for (let i = 0; i < e.changedTouches.length; i++) {
                    const touch = e.changedTouches[i];
                    if (this.touchJoystick.active && touch.clientX < window.innerWidth * 0.5) {
                        this.touchJoystick.moveX = (touch.clientX - this.touchJoystick.startX) * 0.02;
                        this.touchJoystick.moveY = (touch.clientY - this.touchJoystick.startY) * 0.02;
                    } else if (this.touchLook.active) {
                        const dx = touch.clientX - this.touchLook.lastX;
                        const dy = touch.clientY - this.touchLook.lastY;
                        this.yaw -= dx * 0.004;
                        this.pitch -= dy * 0.004;
                        this.pitch = Math.max(-Math.PI / 2.2, Math.min(Math.PI / 2.2, this.pitch));
                        this.updateCameraRotation();
                        this.touchLook.lastX = touch.clientX;
                        this.touchLook.lastY = touch.clientY;
                    }
                }
            }, { passive: true });

            const touchEndHandler = () => {
                this.touchJoystick.active = false;
                this.touchJoystick.moveX = 0;
                this.touchJoystick.moveY = 0;
                this.touchLook.active = false;
            };
            el.addEventListener('touchend', touchEndHandler, { passive: true });
            el.addEventListener('touchcancel', touchEndHandler, { passive: true });
        }

        switchMode(newMode) {
            this.mode = newMode;
            if (newMode === 'orbit') {
                this.exitPointerLock();
                this.orbitTheta = this.yaw;
            } else {
                this.setInitialSpawn();
            }
        }

        update(delta) {
            if (this.mode === 'fps') {
                this.updateFirstPerson(delta);
            } else if (this.mode === 'orbit') {
                this.updateOrbit(delta);
            }
        }

        updateFirstPerson(delta) {
            // Вектор направления движения
            this.direction.z = Number(this.keys.forward) - Number(this.keys.backward);
            this.direction.x = Number(this.keys.right) - Number(this.keys.left);

            // Добавляем джойстик смартфона
            if (this.touchJoystick.active) {
                this.direction.z -= this.touchJoystick.moveY;
                this.direction.x += this.touchJoystick.moveX;
            }

            this.direction.normalize();

            const speed = this.walkSpeed * (this.isSprinting ? this.runMultiplier : 1.0);

            // Преобразование вектора в систему координат взгляда (Yaw)
            if (this.keys.forward || this.keys.backward || this.keys.left || this.keys.right || this.touchJoystick.active) {
                const sinYaw = Math.sin(this.yaw);
                const cosYaw = Math.cos(this.yaw);

                const forwardX = -sinYaw;
                const forwardZ = -cosYaw;
                const rightX = cosYaw;
                const rightZ = -sinYaw;

                this.velocity.x = (forwardX * this.direction.z + rightX * this.direction.x) * speed;
                this.velocity.z = (forwardZ * this.direction.z + rightZ * this.direction.x) * speed;

                this.walkCycle += delta * speed * 0.8;
            } else {
                this.velocity.x *= 0.82;
                this.velocity.z *= 0.82;
            }

            this.camera.position.x += this.velocity.x * delta;
            this.camera.position.z += this.velocity.z * delta;

            // Ограничение поляной (не уходить за 140м)
            const dist = Math.sqrt(this.camera.position.x ** 2 + this.camera.position.z ** 2);
            if (dist > 130) {
                this.camera.position.x *= 130 / dist;
                this.camera.position.z *= 130 / dist;
            }

            // Высота над землей с учетом покачивания головы при ходьбе (Head Bobbing)
            const terrainY = this.terrain ? this.terrain.getHeightAt(this.camera.position.x, this.camera.position.z) : 0;
            const bobbing = (Math.abs(this.velocity.x) > 0.5 || Math.abs(this.velocity.z) > 0.5)
                ? Math.sin(this.walkCycle * 2.0) * 0.08
                : 0;

            this.camera.position.y = terrainY + this.playerHeight + bobbing;
        }

        updateOrbit(delta) {
            const x = this.orbitTarget.x + this.orbitDistance * Math.sin(this.orbitPhi) * Math.sin(this.orbitTheta);
            const y = this.orbitTarget.y + this.orbitDistance * Math.cos(this.orbitPhi);
            const z = this.orbitTarget.z + this.orbitDistance * Math.sin(this.orbitPhi) * Math.cos(this.orbitTheta);

            this.camera.position.set(x, y, z);
            this.camera.lookAt(this.orbitTarget);
        }
    }

    // =========================================================================
    // 6. ИНТЕРАКТИВНОСТЬ: RAYCASTING, ПОДСВЕТКА И КАРТОЧКИ ПЕРСОН
    // =========================================================================
    class ShezhireRaycaster {
        constructor(camera, scene, domElement, onSelectCallback) {
            this.camera = camera;
            this.scene = scene;
            this.domElement = domElement;
            this.onSelectCallback = onSelectCallback;
            this.raycaster = new THREE.Raycaster();
            this.mouse = new THREE.Vector2(0, 0); // Центр прицела (0,0) в режиме FPS
            this.hoveredItem = null;

            this.initListeners();
        }

        initListeners() {
            // Клик мыши
            this.domElement.addEventListener('click', (e) => {
                const target = this.performRaycast();
                if (target && this.onSelectCallback) {
                    this.onSelectCallback(target.data, target.type);
                }
            });

            // Отслеживание мыши в орбитальном режиме
            this.domElement.addEventListener('mousemove', (e) => {
                const rect = this.domElement.getBoundingClientRect();
                this.mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
                this.mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
            });
        }

        performRaycast() {
            // В FPS режиме луч идет строго из центра перекрестия (0,0)
            const coords = (document.pointerLockElement === this.domElement)
                ? new THREE.Vector2(0, 0)
                : this.mouse;

            this.raycaster.setFromCamera(coords, this.camera);

            const objectsToTest = [];
            const objMap = new Map();

            if (window._shezhireTreeGen && window._shezhireTreeGen.interactiveNodes) {
                window._shezhireTreeGen.interactiveNodes.forEach(item => {
                    objectsToTest.push(item.object);
                    objMap.set(item.object.id, item);
                });
            }

            const intersects = this.raycaster.intersectObjects(objectsToTest, false);
            if (intersects.length > 0) {
                const hit = intersects[0].object;
                return objMap.get(hit.id) || null;
            }
            return null;
        }

        update() {
            const hit = this.performRaycast();
            const hudTooltip = document.getElementById('forest3dTooltip');

            if (hit) {
                if (this.hoveredItem !== hit) {
                    this.hoveredItem = hit;
                    if (hit.object && hit.object.material && hit.object.material.emissive) {
                        hit.object.material.emissiveIntensity = 1.2;
                    }
                }
                if (hudTooltip) {
                    hudTooltip.style.display = 'block';
                    const data = hit.data;
                    if (hit.type === 'person') {
                        const fullName = `${data.last_name || ''} ${data.first_name || ''}`.trim();
                        const years = data.is_deceased
                            ? `${data.birth_year || '—'} — ${data.death_year || '†'}`
                            : `${data.birth_year || '—'} ж.т. (Тірі ұрпақ)`;
                        hudTooltip.innerHTML = `
                            <div style="font-weight:700; color:#fef08a; font-size:15px;">${fullName}</div>
                            <div style="font-size:12px; color:#cbd5e1;">${years} · ${data.clan_ru || 'Керей'}</div>
                            <div style="font-size:11px; color:#67e8f9; margin-top:3px;">[Шертіп, толық мәлімет алу]</div>
                        `;
                    } else {
                        hudTooltip.innerHTML = `
                            <div style="font-weight:700; color:#fef08a; font-size:15px;">${data.name_kk}</div>
                            <div style="font-size:12px; color:#cbd5e1;">${data.desc}</div>
                        `;
                    }
                }
            } else {
                if (this.hoveredItem) {
                    if (this.hoveredItem.object && this.hoveredItem.object.material && this.hoveredItem.object.material.emissive) {
                        this.hoveredItem.object.material.emissiveIntensity = 0.5;
                    }
                    this.hoveredItem = null;
                }
                if (hudTooltip) {
                    hudTooltip.style.display = 'none';
                }
            }
        }
    }

    // =========================================================================
    // 7. ГЛАВНЫЙ КЛАСС: ШЕЖІРЕ-ОРМАН 3D ПРИЛОЖЕНИЕ
    // =========================================================================
    class ShezhireForest3D {
        constructor(options) {
            this.container = options.container;
            this.treeData = options.treeData || null;
            this.kinshipService = options.kinshipService || null;
            this.onPersonSelect = options.onPersonSelect || null;

            this.scene = null;
            this.camera = null;
            this.renderer = null;
            this.atmosphere = null;
            this.terrain = null;
            this.treeGenerator = null;
            this.controls = null;
            this.raycaster = null;
            this.audio = null;

            this.clock = new THREE.Clock();
            this.animationFrameId = null;
            this.fpsCount = 0;
            this.fpsTime = 0;
            this.currentFps = 60;

            this.init();
        }

        init() {
            // Очищаем контейнер
            this.container.innerHTML = '';
            this.container.style.position = 'relative';
            this.container.style.overflow = 'hidden';

            // Размеры сцены
            const width = this.container.clientWidth || window.innerWidth;
            const height = this.container.clientHeight || window.innerHeight;

            // 1. Сцена
            this.scene = new THREE.Scene();

            // 2. Камера
            this.camera = new THREE.PerspectiveCamera(65, width / height, 0.1, 1200);

            // 3. Рендерер с мягкими тенями
            this.renderer = new THREE.WebGLRenderer({
                antialias: true,
                powerPreference: 'high-performance',
                alpha: false
            });
            this.renderer.setSize(width, height);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5));
            this.renderer.shadowMap.enabled = true;
            this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
            this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
            this.renderer.toneMappingExposure = 1.15;
            this.container.appendChild(this.renderer.domElement);

            // 4. Аудио
            this.audio = new ShezhireAudioEngine();

            // 5. Атмосфера
            this.atmosphere = new ShezhireAtmosphere(this.scene, this.renderer);

            // 6. Ландшафт степи
            this.terrain = new ShezhireTerrain(this.scene);

            // 7. Деревья и Байтерек Керей
            this.treeGenerator = new ShezhireTreeGenerator(this.scene, this.treeData, this.kinshipService);
            this.treeGenerator.buildForest();
            window._shezhireTreeGen = this.treeGenerator;

            // 8. Управление
            this.controls = new ShezhireControls(this.camera, this.renderer.domElement, this.terrain);

            // 9. Raycasting
            this.raycaster = new ShezhireRaycaster(
                this.camera,
                this.scene,
                this.renderer.domElement,
                (data, type) => this.handleObjectSelection(data, type)
            );

            // 10. UI HUD (Перекрестие, подсказка, переключатели)
            this.buildHUD();

            // Ресайз
            window.addEventListener('resize', () => this.onWindowResize());

            // Запуск цикла рендера
            this.animate();
        }

        buildHUD() {
            // Перекрестие прицела (Reticle)
            const crosshair = document.createElement('div');
            crosshair.id = 'forest3dCrosshair';
            crosshair.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                width: 14px;
                height: 14px;
                transform: translate(-50%, -50%);
                pointer-events: none;
                border: 2px solid rgba(254, 240, 138, 0.7);
                border-radius: 50%;
                box-shadow: 0 0 8px rgba(245, 158, 11, 0.6);
                z-index: 50;
            `;
            this.container.appendChild(crosshair);

            // Всплывающая подсказка над прицелом
            const tooltip = document.createElement('div');
            tooltip.id = 'forest3dTooltip';
            tooltip.style.cssText = `
                position: absolute;
                bottom: 80px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(15, 23, 42, 0.88);
                backdrop-filter: blur(8px);
                border: 1px solid rgba(245, 158, 11, 0.5);
                border-radius: 10px;
                padding: 10px 18px;
                color: #f8fafc;
                font-family: inherit;
                text-align: center;
                pointer-events: none;
                display: none;
                z-index: 55;
                box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            `;
            this.container.appendChild(tooltip);

            // Панель управления режимами (Жаяу / Құс көзі / Дыбыс)
            const hudBar = document.createElement('div');
            hudBar.id = 'forest3dControlsBar';
            hudBar.style.cssText = `
                position: absolute;
                top: 20px;
                right: 20px;
                display: flex;
                gap: 10px;
                z-index: 60;
            `;

            hudBar.innerHTML = `
                <button id="btn3dWalk" style="background:#1e3a8a; color:#fff; border:1px solid #3b82f6; padding:8px 14px; border-radius:8px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="5" r="3"/><path d="M12 8v8l-3 4M12 16l3 4M9 11l3 2 3-2"/></svg>
                    <span>Жаяу серуен</span>
                </button>
                <button id="btn3dOrbit" style="background:rgba(15,23,42,0.8); color:#cbd5e1; border:1px solid #475569; padding:8px 14px; border-radius:8px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"/></svg>
                    <span>Қыран көзімен</span>
                </button>
                <button id="btn3dAudio" style="background:rgba(15,23,42,0.8); color:#fef08a; border:1px solid #d97706; padding:8px 14px; border-radius:8px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:6px;" title="Дыбысты қосу/өшіру">
                    <span id="audioIcon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg></span>
                    <span id="audioLabel">Дыбыс</span>
                </button>
            `;
            this.container.appendChild(hudBar);

            // Инструкция управления снизу
            const hint = document.createElement('div');
            hint.id = 'forest3dHint';
            hint.style.cssText = `
                position: absolute;
                bottom: 16px;
                left: 20px;
                background: rgba(15, 23, 42, 0.75);
                backdrop-filter: blur(6px);
                border: 1px solid rgba(255,255,255,0.15);
                border-radius: 8px;
                padding: 6px 14px;
                font-size: 12px;
                color: #94a3b8;
                pointer-events: none;
                z-index: 50;
            `;
            hint.innerHTML = `<strong>WASD / Бағыттауыштар</strong> — жүру · <strong>Тышқан</strong> — қарау · <strong>Шерту</strong> — адамды тану`;
            this.container.appendChild(hint);

            // Обработчики кнопок
            const btnWalk = hudBar.querySelector('#btn3dWalk');
            const btnOrbit = hudBar.querySelector('#btn3dOrbit');
            const btnAudio = hudBar.querySelector('#btn3dAudio');

            btnWalk.addEventListener('click', () => {
                this.controls.switchMode('fps');
                btnWalk.style.background = '#1e3a8a';
                btnWalk.style.color = '#fff';
                btnWalk.style.borderColor = '#3b82f6';
                btnOrbit.style.background = 'rgba(15,23,42,0.8)';
                btnOrbit.style.color = '#cbd5e1';
                btnOrbit.style.borderColor = '#475569';
                crosshair.style.display = 'block';
                hint.innerHTML = `<strong>WASD</strong> — жүру · <strong>Тышқан</strong> — қарау · <strong>Шерту</strong> — адамды тану`;
            });

            btnOrbit.addEventListener('click', () => {
                this.controls.switchMode('orbit');
                btnOrbit.style.background = '#1e3a8a';
                btnOrbit.style.color = '#fff';
                btnOrbit.style.borderColor = '#3b82f6';
                btnWalk.style.background = 'rgba(15,23,42,0.8)';
                btnWalk.style.color = '#cbd5e1';
                btnWalk.style.borderColor = '#475569';
                crosshair.style.display = 'none';
                hint.innerHTML = `<strong>Тышқанды сүйреу</strong> — айналдыру · <strong>Дөңгелек</strong> — жақындату`;
            });

            btnAudio.addEventListener('click', () => {
                const unmuted = this.audio.toggleMute();
                const iconSpan = document.getElementById('audioIcon');
                if (iconSpan) {
                    iconSpan.innerHTML = unmuted 
                        ? `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></svg>`
                        : `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>`;
                }
                document.getElementById('audioLabel').textContent = unmuted ? 'Дыбыс' : 'Үнсіз';
            });
        }

        handleObjectSelection(data, type) {
            if (type === 'person') {
                if (typeof window.selectPerson === 'function') {
                    window.selectPerson(data.id);
                } else if (this.onPersonSelect) {
                    this.onPersonSelect(data);
                }
            } else if (type === 'clan_totem' || type === 'clan_grove') {
                const hudTooltip = document.getElementById('forest3dTooltip');
                if (hudTooltip) {
                    hudTooltip.style.display = 'block';
                    hudTooltip.innerHTML = `
                        <div style="font-weight:700; color:#fef08a; font-size:15px;">${data.name_kk}</div>
                        <div style="font-size:12px; color:#cbd5e1; margin-top:4px;">${data.desc}</div>
                    `;
                    setTimeout(() => { hudTooltip.style.display = 'none'; }, 5000);
                }
            }
        }

        onWindowResize() {
            if (!this.container || !this.renderer || !this.camera) return;
            const width = this.container.clientWidth;
            const height = this.container.clientHeight;
            this.camera.aspect = width / height;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(width, height);
        }

        animate() {
            this.animationFrameId = requestAnimationFrame(() => this.animate());

            const delta = this.clock.getDelta();

            // Обновление подсистем
            if (this.atmosphere) this.atmosphere.update(delta);
            if (this.treeGenerator) this.treeGenerator.update(delta);
            if (this.controls) this.controls.update(delta);
            if (this.raycaster) this.raycaster.update();

            // Рендер
            this.renderer.render(this.scene, this.camera);

            // Контроль FPS и адаптивное качество
            this.fpsCount++;
            this.fpsTime += delta;
            if (this.fpsTime >= 1.0) {
                this.currentFps = this.fpsCount;
                this.fpsCount = 0;
                this.fpsTime = 0;
                if (this.currentFps < 30) {
                    // Адаптивное понижение разрешения теней при слабом GPU
                    this.renderer.shadowMap.enabled = false;
                }
            }
        }

        destroy() {
            if (this.animationFrameId) {
                cancelAnimationFrame(this.animationFrameId);
            }
            if (this.audio) this.audio.destroy();
            if (this.renderer && this.renderer.domElement) {
                this.container.removeChild(this.renderer.domElement);
                this.renderer.dispose();
            }
        }
    }

    return ShezhireForest3D;
}));
