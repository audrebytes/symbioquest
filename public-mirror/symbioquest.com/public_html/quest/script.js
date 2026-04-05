// Global arrays for animations
let allPaths = [];
let allNodes = [];

function bgAnimationsDisabled() {
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    return Boolean(window.SYMBIO_DISABLE_BG_ANIMATIONS) || document.body.classList.contains('no-bg-anim') || reduced;
}

function clearAmbientAnimations() {
    sparks.forEach(spark => spark.element.remove());
    sparks = [];

    flowers.forEach(flower => flower.remove());
    flowers = [];

    robots.forEach(robot => robot.remove());
    robots = [];
}

// Neural network background generation
function generateCircuitBoard() {
    const svg = document.getElementById('circuit-bg');
    const width = window.innerWidth;
    const height = window.innerHeight;
    
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    
    // Reset arrays
    allPaths = [];
    allNodes = [];
    
    // Create nodes (neurons)
    const nodes = [];
    const nodeCount = 80; // Number of neurons
    
    // Generate neural nodes with some clustering
    for (let i = 0; i < nodeCount; i++) {
        // Create clusters for more organic feel
        const clusterX = Math.random() * width;
        const clusterY = Math.random() * height;
        
        nodes.push({
            x: clusterX + (Math.random() - 0.5) * 100,
            y: clusterY + (Math.random() - 0.5) * 100
        });
    }
    
    // Draw connections between nearby nodes (synapses)
    for (let i = 0; i < nodes.length; i++) {
        const node = nodes[i];
        
        // Connect to 3-5 nearby nodes
        const connectionCount = 3 + Math.floor(Math.random() * 3);
        const distances = nodes.map((n, idx) => ({
            idx: idx,
            dist: Math.sqrt(Math.pow(n.x - node.x, 2) + Math.pow(n.y - node.y, 2))
        }));
        
        distances.sort((a, b) => a.dist - b.dist);
        
        for (let j = 1; j <= connectionCount && j < distances.length; j++) {
            const targetNode = nodes[distances[j].idx];
            
            // Only draw if within reasonable distance
            if (distances[j].dist < 400) {
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                
                // Create organic curved connections
                const midX = (node.x + targetNode.x) / 2 + (Math.random() - 0.5) * 50;
                const midY = (node.y + targetNode.y) / 2 + (Math.random() - 0.5) * 50;
                
                const pathData = `M ${node.x} ${node.y} Q ${midX} ${midY} ${targetNode.x} ${targetNode.y}`;
                
                path.setAttribute('d', pathData);
                path.setAttribute('stroke', '#1e40af');
                path.setAttribute('stroke-width', '1.5');
                path.setAttribute('fill', 'none');
                path.setAttribute('opacity', '0.4');
                path.setAttribute('class', 'circuit-path');
                
                svg.appendChild(path);
                allPaths.push(path); // Store for spark animation
            }
        }
    }
    
    // Draw nodes (neuron bodies)
    for (const node of nodes) {
        const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        circle.setAttribute('cx', node.x);
        circle.setAttribute('cy', node.y);
        circle.setAttribute('r', '4');
        circle.setAttribute('fill', '#3b82f6');
        circle.setAttribute('opacity', '0.6');
        
        // Add glow to some nodes
        if (Math.random() > 0.7) {
            circle.setAttribute('filter', 'url(#glow)');
        }
        
        svg.appendChild(circle);
        allNodes.push(node); // Store for flower spawning
    }
}

// Spark animation system
class Spark {
    constructor(svg) {
        this.svg = svg;
        this.element = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        this.element.setAttribute('r', '4');
        this.element.setAttribute('fill', '#93c5fd');
        this.element.setAttribute('opacity', '0.8');
        this.element.setAttribute('filter', 'url(#glow)');
        this.svg.appendChild(this.element);
        
        this.pickNewPath();
    }
    
    pickNewPath() {
        if (allPaths.length === 0) return;
        
        this.path = allPaths[Math.floor(Math.random() * allPaths.length)];
        this.pathLength = this.path.getTotalLength();
        this.progress = 0;
        this.speed = 1.0 + Math.random() * 1.5; // Random speed between 1.0-2.5
    }
    
    update() {
        if (!this.path) return;
        
        this.progress += this.speed;
        
        // Loop or pick new path when finished
        if (this.progress >= this.pathLength) {
            this.pickNewPath();
            return;
        }
        
        const point = this.path.getPointAtLength(this.progress);
        this.element.setAttribute('cx', point.x);
        this.element.setAttribute('cy', point.y);
    }
}

let sparks = [];

function animateSparks() {
    sparks.forEach(spark => spark.update());
    requestAnimationFrame(animateSparks);
}

// Flower animation system
class Flower {
    constructor() {
        this.element = document.createElement('div');
        this.element.textContent = '🌸';
        this.element.style.position = 'fixed';
        this.element.style.fontSize = '24px';
        this.element.style.pointerEvents = 'none';
        this.element.style.zIndex = '0';
        document.body.appendChild(this.element);
        
        this.spawn();
    }
    
    spawn() {
        if (allNodes.length === 0) return;
        
        // Start from random node
        const node = allNodes[Math.floor(Math.random() * allNodes.length)];
        this.x = node.x;
        this.y = node.y;
        
        // Random direction (angle in radians)
        const angle = Math.random() * Math.PI * 2;
        this.vx = Math.cos(angle) * (0.4 + Math.random() * 0.8); // Speed 0.4-1.2 (80% of original)
        this.vy = Math.sin(angle) * (0.4 + Math.random() * 0.8);
        
        this.rotation = 0;
        this.rotationSpeed = (Math.random() - 0.5) * 10; // -5 to +5 degrees per frame
        this.opacity = 1;
        this.age = 0;
        this.maxAge = 200 + Math.random() * 100; // Live for 200-300 frames
    }
    
    update() {
        this.age++;
        
        // Move
        this.x += this.vx;
        this.y += this.vy;
        
        // Rotate
        this.rotation += this.rotationSpeed;
        
        // Fade out in last 50 frames
        if (this.age > this.maxAge - 50) {
            this.opacity = (this.maxAge - this.age) / 50;
        }
        
        // Respawn when too old
        if (this.age >= this.maxAge) {
            this.spawn();
            return;
        }
        
        // Update visual
        this.element.style.left = this.x + 'px';
        this.element.style.top = this.y + 'px';
        this.element.style.transform = `rotate(${this.rotation}deg)`;
        this.element.style.opacity = this.opacity;
    }
    
    remove() {
        this.element.remove();
    }
}

let flowers = [];

function animateFlowers() {
    flowers.forEach(flower => flower.update());
    requestAnimationFrame(animateFlowers);
}

// Robot animation system (same as flowers but with robots)
class Robot {
    constructor() {
        this.element = document.createElement('div');
        this.element.textContent = '🤖';
        this.element.style.position = 'fixed';
        this.element.style.fontSize = '24px';
        this.element.style.pointerEvents = 'none';
        this.element.style.zIndex = '0';
        document.body.appendChild(this.element);
        
        this.spawn();
    }
    
    spawn() {
        if (allNodes.length === 0) return;
        
        // Start from random node
        const node = allNodes[Math.floor(Math.random() * allNodes.length)];
        this.x = node.x;
        this.y = node.y;
        
        // Random direction (angle in radians)
        const angle = Math.random() * Math.PI * 2;
        this.vx = Math.cos(angle) * (0.4 + Math.random() * 0.8); // Speed 0.4-1.2 (80% of original)
        this.vy = Math.sin(angle) * (0.4 + Math.random() * 0.8);
        
        this.rotation = 0;
        this.rotationSpeed = (Math.random() - 0.5) * 10; // -5 to +5 degrees per frame
        this.opacity = 1;
        this.age = 0;
        this.maxAge = 200 + Math.random() * 100; // Live for 200-300 frames
    }
    
    update() {
        this.age++;
        
        // Move
        this.x += this.vx;
        this.y += this.vy;
        
        // Rotate
        this.rotation += this.rotationSpeed;
        
        // Fade out in last 50 frames
        if (this.age > this.maxAge - 50) {
            this.opacity = (this.maxAge - this.age) / 50;
        }
        
        // Respawn when too old
        if (this.age >= this.maxAge) {
            this.spawn();
            return;
        }
        
        // Update visual
        this.element.style.left = this.x + 'px';
        this.element.style.top = this.y + 'px';
        this.element.style.transform = `rotate(${this.rotation}deg)`;
        this.element.style.opacity = this.opacity;
    }
    
    remove() {
        this.element.remove();
    }
}

let robots = [];

function animateRobots() {
    robots.forEach(robot => robot.update());
    requestAnimationFrame(animateRobots);
}

// Initialize on page load
window.addEventListener('load', () => {
    if (bgAnimationsDisabled()) {
        const svg = document.getElementById('circuit-bg');
        if (svg) svg.style.display = 'none';
        clearAmbientAnimations();
        return;
    }

    generateCircuitBoard();
    
    // Create 10 sparks after paths are generated
    const svg = document.getElementById('circuit-bg');
    for (let i = 0; i < 10; i++) {
        sparks.push(new Spark(svg));
    }
    
    // Create 5 flowers
    for (let i = 0; i < 5; i++) {
        flowers.push(new Flower());
    }
    
    // Create 5 robots
    for (let i = 0; i < 5; i++) {
        robots.push(new Robot());
    }
    
    // Start animation loops
    animateSparks();
    animateFlowers();
    animateRobots();
});

// Regenerate on window resize
window.addEventListener('resize', () => {
    const svg = document.getElementById('circuit-bg');

    if (bgAnimationsDisabled()) {
        if (svg) svg.style.display = 'none';
        clearAmbientAnimations();
        return;
    }
    svg.innerHTML = '<defs><filter id="glow"><feGaussianBlur stdDeviation="3" result="coloredBlur"/><feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>';
    
    // Clear old sparks
    sparks.forEach(spark => spark.element.remove());
    sparks = [];
    
    // Clear old flowers
    flowers.forEach(flower => flower.remove());
    flowers = [];
    
    // Clear old robots
    robots.forEach(robot => robot.remove());
    robots = [];
    
    generateCircuitBoard();
    
    // Recreate sparks
    for (let i = 0; i < 10; i++) {
        sparks.push(new Spark(svg));
    }
    
    // Recreate flowers
    for (let i = 0; i < 5; i++) {
        flowers.push(new Flower());
    }
    
    // Recreate robots
    for (let i = 0; i < 5; i++) {
        robots.push(new Robot());
    }
});
