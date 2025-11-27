// Enhanced form validation for all question types
document.querySelector('form').addEventListener('submit', function(e) {
    let isValid = true;
    let firstError = null;

    document.querySelectorAll('.evaluation-question').forEach(question => {
        const questionId = question.dataset.questionId;
        const questionType = question.dataset.questionType;
        const errorMsg = question.querySelector('.error-message');
        let isAnswered = false;

        // Check answer based on question type
        switch(questionType) {
            case 'evaluation':
                const ratingInput = document.getElementById(`question[${questionId}][rating]`);
                isAnswered = ratingInput ? ratingInput.value !== '0' : false;
                break;

            case 'true_false':
                const answerInput = document.getElementById(`question-${questionId}-answer`);
                isAnswered = answerInput.value !== '';
                break;

            case 'essay':
                const essayInput = question.querySelector('textarea');
                isAnswered = essayInput.value.trim() !== '';
                break;

            case 'multiple_choice':
                const checkedRadio = question.querySelector('input[type="radio"]:checked');
                isAnswered = checkedRadio !== null;
                break;
        }

        // Update validation state
        if (!isAnswered) {
            isValid = false;
            errorMsg.style.display = 'block';
            if (!firstError) firstError = question;
        } else {
            errorMsg.style.display = 'none';
        }
    });

    if (!isValid) {
        e.preventDefault();
        alert('الرجاء الإجابة على جميع الأسئلة المطلوبة');
        if (firstError) {
            firstError.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }
    }
});

// Star rating interaction
document.addEventListener('click', function(e) {
    if (e.target.closest('.stars i')) {
        const star = e.target;
        const question = star.closest('.evaluation-question');
        const questionId = question.dataset.questionId;
        const stars = question.querySelectorAll('.stars i');
        const ratingInput = document.getElementById(`question[${questionId}][rating]`);

        stars.forEach((s, index) => {
            s.classList.toggle('active', index <= star.dataset.value - 1);
        });
        
        ratingInput.value = star.dataset.value;
        question.querySelector('.error-message').style.display = 'none';
    }
});

// Boolean answer interaction
function selectAnswer(questionId, answer) {
    const question = document.querySelector(`.evaluation-question[data-question-id="${questionId}"]`);
    const answerInput = document.getElementById(`question-${questionId}-answer`);
    const options = question.querySelectorAll('.boolean-option');

    options.forEach(opt => opt.classList.remove('active'));
    answerInput.value = answer;
    question.querySelector(`.boolean-option--${answer}`).classList.add('active');
    question.querySelector('.error-message').style.display = 'none';
}

// Multiple choice interaction
document.addEventListener('change', function(e) {
    if (e.target.matches('input[type="radio"]')) {
        const question = e.target.closest('.evaluation-question');
        question.querySelector('.error-message').style.display = 'none';
    }
});

// Essay input interaction
document.addEventListener('input', function(e) {
    if (e.target.matches('textarea')) {
        const question = e.target.closest('.evaluation-question');
        question.querySelector('.error-message').style.display = 'none';
    }
});