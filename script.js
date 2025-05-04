document.addEventListener('DOMContentLoaded', () => {
    const imageUpload = document.getElementById('imageUpload');
    const fileNameDisplay = document.getElementById('fileName');
    const imagePreview = document.getElementById('imagePreview');
    const previewPlaceholder = document.getElementById('previewPlaceholder');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const scanButton = document.getElementById('scanButton');
    const resultsSection = document.getElementsByClassName('results-section')[0];
    const loadingIndicator = document.getElementById('loadingIndicator');
    const errorDisplay = document.getElementById('errorDisplay');
    const resultContent = document.getElementById('resultContent');
    const probabilityText = document.getElementById('probabilityText');
    const reasoningText = document.getElementById('reasoningText');
    const isAdultText = document.getElementById('isAdultText');

    let selectedFile = null;

    // 文件选择处理
    imageUpload.addEventListener('change', (event) => {
        selectedFile = event.target.files[0];
        if (selectedFile) {
            // 检查文件类型 (基本检查)
            const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!allowedTypes.includes(selectedFile.type)) {
                showError('不支持的文件类型。请上传 JPG, PNG, WEBP 或 GIF 图片。');
                resetPreview();
                selectedFile = null;
                scanButton.disabled = true;
                return;
            }

            // 检查文件大小 (例如：限制 10MB)
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (selectedFile.size > maxSize) {
                showError(`文件过大。请上传小于 ${maxSize / 1024 / 1024}MB 的图片。`);
                resetPreview();
                selectedFile = null;
                scanButton.disabled = true;
                return;
            }

            fileNameDisplay.textContent = selectedFile.name;
            scanButton.disabled = false; // 允许点击分析按钮
            hideError(); // 清除之前的错误

            // 显示预览
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                previewPlaceholder.style.display = 'none'; // 隐藏占位符
            }
            reader.readAsDataURL(selectedFile);
            resetResults(); // 重置结果区域

        } else {
            resetPreview();
            scanButton.disabled = true;
        }
    });

    // 点击分析按钮
    scanButton.addEventListener('click', () => {
        if (!selectedFile) {
            showError('请先选择一个图片文件。');
            return;
        }

        // 准备 FormData
        const formData = new FormData();
        formData.append('image', selectedFile);

        // 显示加载状态，隐藏旧结果和错误
        resultsSection.style.display = 'block';
        loadingIndicator.style.display = 'block';
        resultContent.style.display = 'none';
        errorDisplay.style.display = 'none';
        scanButton.disabled = true; // 防止重复点击

        // 发送 AJAX 请求到后端
        fetch('scan.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                // 如果 HTTP 状态码不是 2xx，也认为是错误
                return response.json().then(errData => {
                    throw new Error(errData.error || `服务器错误: ${response.status}`);
                });
            }
            return response.json(); // 解析 JSON 数据
        })
        .then(data => {
            loadingIndicator.style.display = 'none'; // 隐藏加载
            if (data.error) {
                showError(data.error); // 显示后端返回的错误信息
            } else if (data.result) {
                displayResults(data.result); // 显示成功的结果
            } else {
                showError('收到无效的响应格式。'); // 处理未知格式
            }
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            loadingIndicator.style.display = 'none';
            showError(`请求失败: ${error.message}`); // 显示捕获到的错误
        })
        .finally(() => {
            // 无论成功或失败，重新启用按钮（除非仍在加载）
            if (loadingIndicator.style.display === 'none') {
               scanButton.disabled = false;
            }
            // 清理文件选择，避免用户不选新文件直接点分析旧文件
            // imageUpload.value = ''; // 这会触发 change 事件，可能导致问题，暂时注释
            // selectedFile = null;
            // fileNameDisplay.textContent = '未选择文件';
            // scanButton.disabled = true; // 需要重新选择文件才能分析
        });
    });

    // 显示结果
    function displayResults(result) {
        resultContent.style.display = 'block';
        errorDisplay.style.display = 'none';

        const probability = parseFloat(result.probability) * 100; // 转为百分比
        probabilityText.textContent = `${probability.toFixed(1)}%`;

        // 根据风险概率设置不同的颜色
        if (probability < 30) {
            probabilityText.style.backgroundColor = '#4CAF50'; // 绿色 - 安全
        } else if (probability < 50) {
            probabilityText.style.backgroundColor = '#8BC34A'; // 浅绿色 - 较安全
        } else if (probability < 70) {
            probabilityText.style.backgroundColor = '#FFC107'; // 黄色 - 警告
        } else if (probability < 85) {
            probabilityText.style.backgroundColor = '#FF9800'; // 橙色 - 高风险
        } else {
            probabilityText.style.backgroundColor = '#F44336'; // 红色 - 非常高风险
        }

        reasoningText.textContent = result.reasoning || 'AI 未提供具体原因。';
        isAdultText.textContent = result.is_adult_content ? '是' : '否';
    }

    // 重置预览区域
    function resetPreview() {
        fileNameDisplay.textContent = '未选择文件';
        imagePreview.src = '#';
        imagePreview.style.display = 'none';
        previewPlaceholder.style.display = 'block'; // 显示占位符
    }

    // 重置结果区域
    function resetResults() {
        resultsSection.style.display = 'none';
        loadingIndicator.style.display = 'none';
        errorDisplay.style.display = 'none';
        resultContent.style.display = 'none';
        probabilityText.textContent = '--%';
        probabilityText.style.backgroundColor = ''; // 重置背景颜色
        reasoningText.textContent = '--';
        isAdultText.textContent = '--';
    }

    // 显示错误消息
    function showError(message) {
        resultsSection.style.display = 'block'; // 确保结果区域可见以显示错误
        errorDisplay.textContent = message;
        errorDisplay.style.display = 'block';
        resultContent.style.display = 'none'; // 隐藏正常结果区域
        loadingIndicator.style.display = 'none'; // 隐藏加载指示器
    }

    // 隐藏错误消息
    function hideError() {
        errorDisplay.style.display = 'none';
    }
});