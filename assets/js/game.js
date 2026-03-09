const recipes = {
    "Cà Phê Sữa": ["Sữa", "Cafe", "Đá"],
    "Cà Phê Đen": ["Cafe", "Cafe", "Đá"],
    "Bạc Xỉu": ["Sữa", "Sữa", "Cafe", "Đá"],
    "Cà Phê Đường": ["Cafe", "Đường", "Đá"]
};

let userMix = [];
let currentOrder = "";
let patience = 100;
let patienceTimer;

function spawnCustomer() {
    userMix = [];
    document.getElementById('cup').innerHTML = '';
    const wrap = document.getElementById('customer-wrap');
    
    // Tạo khách ngẫu nhiên
    const seed = Math.floor(Math.random() * 1000);
    document.getElementById('customer-img').src = `https://api.dicebear.com/7.x/avataaars/svg?seed=${seed}`;
    
    // Chọn món
    const names = Object.keys(recipes);
    currentOrder = names[Math.floor(Math.random() * names.length)];
    document.getElementById('order-text').innerText = currentOrder;

    // Hiệu ứng đi vào
    wrap.classList.remove('hidden', 'animate__slideOutRight');
    wrap.classList.add('flex', 'animate__slideInLeft');

    startPatience();
}

function startPatience() {
    patience = 100;
    clearInterval(patienceTimer);
    patienceTimer = setInterval(() => {
        patience -= 1.5;
        document.getElementById('patience-bar').style.width = patience + "%";
        if (patience <= 0) {
            clearInterval(patienceTimer);
            Swal.fire("Giận quá!", "Khách đợi lâu quá nên bỏ đi rồi!", "error");
            nextCustomerAnimate();
        }
    }, 200);
}

function addIng(ing) {
    if (!currentOrder) return;
    userMix.push(ing);
    const item = document.createElement('div');
    item.className = "h-8 w-full rounded-lg animate__animated animate__fadeInDown flex items-center justify-center text-[10px] font-bold text-white shadow-sm";
    const colors = {'Sữa': 'bg-blue-300', 'Cafe': 'bg-stone-900', 'Đá': 'bg-cyan-200 text-cyan-700', 'Đường': 'bg-yellow-200 text-yellow-800'};
    item.classList.add(colors[ing]);
    item.innerText = ing;
    document.getElementById('cup').appendChild(item);
}

function serve() {
    if (!currentOrder) return;
    clearInterval(patienceTimer);
    
    if (JSON.stringify(userMix) === JSON.stringify(recipes[currentOrder])) {
        const bonus = Math.floor(patience / 10);
        const total = 10 + bonus;
        updatePoints(total);
        Swal.fire("Tuyệt vời!", `+${total} điểm (Thưởng tốc độ: ${bonus})`, "success");
    } else {
        Swal.fire("Sai món!", "Khách không trả tiền đâu!", "error");
    }
    nextCustomerAnimate();
}

function nextCustomerAnimate() {
    currentOrder = "";
    const wrap = document.getElementById('customer-wrap');
    wrap.classList.replace('animate__slideInLeft', 'animate__slideOutRight');
    setTimeout(() => wrap.classList.add('hidden'), 1000);
}

function updatePoints(pts) {
    const data = new FormData();
    data.append('points', pts);
    fetch('api/save_score.php', { method: 'POST', body: data })
    .then(r => r.json())
    .then(d => document.getElementById('user-points').innerText = d.new_total);
}