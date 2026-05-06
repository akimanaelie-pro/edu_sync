<?php if (!isLoggedIn()) return; ?>
</div>

<style>
#quickAddTabs .list-group-item-action.active {
    background: var(--primary);
    color: white;
}
#quickAddTabs .list-group-item-action {
    border: none;
    border-radius: 0;
    padding: 12px;
    cursor: pointer;
}
#quickAddTabs .list-group-item-action:hover {
    background: #f3f4f6;
}
#quickAddTabs .list-group-item-action.active:hover {
    background: var(--primary);
}
</style>

<div class="modal fade" id="quickAddModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Quick Add</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <div class="col-3 border-end">
                        <div class="list-group list-group-flush" id="quickAddTabs">
                            <button class="list-group-item list-group-item-action active" data-type="teacher">
                                <i class="fas fa-chalkboard-teacher me-2"></i>Teacher
                            </button>
                            <button class="list-group-item list-group-item-action" data-type="student">
                                <i class="fas fa-user-graduate me-2"></i>Student
                            </button>
                            <button class="list-group-item list-group-item-action" data-type="class">
                                <i class="fas fa-door-open me-2"></i>Class
                            </button>
                            <button class="list-group-item list-group-item-action" data-type="subject">
                                <i class="fas fa-book me-2"></i>Subject
                            </button>
                        </div>
                    </div>
                    <div class="col-9">
                        <form id="quickAddForm" class="p-3">
                            <input type="hidden" name="type" value="teacher">
                            
                            <div id="teacherFields">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="teacher_email" class="form-control" placeholder="teacher@school.edu" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="teacher_first_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="teacher_last_name" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="teacher_phone" class="form-control" placeholder="+2507xxxxxxx">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" name="teacher_department" class="form-control" placeholder="e.g. Mathematics">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Qualification</label>
                                    <input type="text" name="teacher_qualification" class="form-control" placeholder="e.g. BSc">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="text" name="teacher_password" class="form-control" placeholder="Default: teacher123">
                                </div>
                            </div>
                            
                            <div id="studentFields" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="student_first_name" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="student_last_name" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="student_email" class="form-control" placeholder="student@school.edu">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Phone (Parent)</label>
                                    <input type="text" name="student_phone" class="form-control" placeholder="+2507xxxxxxx">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="student_gender" class="form-select">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date of Birth</label>
                                    <input type="date" name="student_date_of_birth" class="form-control">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Class</label>
                                    <select name="student_class_id" class="form-select">
                                        <option value="">Select Class</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="classFields" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">Class Name</label>
                                    <input type="text" name="class_name" class="form-control" placeholder="e.g. Primary 1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Grade Level</label>
                                    <input type="text" name="class_grade_level" class="form-control" placeholder="e.g. Primary">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Room Number</label>
                                    <input type="text" name="class_room_number" class="form-control" placeholder="e.g. Room 101">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Capacity</label>
                                    <input type="number" name="class_capacity" class="form-control" value="40">
                                </div>
                            </div>
                            
                            <div id="subjectFields" style="display:none;">
                                <div class="mb-3">
                                    <label class="form-label">Subject Name</label>
                                    <input type="text" name="subject_name" class="form-control" placeholder="e.g. Mathematics" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Subject Code</label>
                                    <input type="text" name="subject_code" class="form-control" placeholder="e.g. MATH">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Class</label>
                                    <select name="subject_class_id" class="form-select">
                                        <option value="">Select Class</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Teacher</label>
                                    <select name="subject_teacher_id" class="form-select">
                                        <option value="">Select Teacher</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Credit Hours</label>
                                    <input type="number" name="subject_credit_hours" class="form-control" value="1">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-1"></i>Add <span id="btnType">Teacher</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
<script>
    // Register Service Worker for Offline Mode
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sms/service-worker.js')
                .then((reg) => {
                    console.log('Service Worker registered:', reg);
                })
                .catch((err) => console.log('Service Worker registration failed:', err));
        });
    }
    
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('active');
    }
    
    function logout() {
        Swal.fire({
            title: 'Logout?',
            text: 'Are you sure you want to logout?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, logout',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('<?= SITE_URL ?>/config/auth.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=logout'
                }).then(() => window.location.href = '<?= SITE_URL ?>/login.php');
            }
        });
    }
    
    function showToast(message, type = 'success') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000
        });
    }
    
    function confirmDelete(title, text) {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ef4444'
        });
    }
    
    const isOnline = navigator.onLine;
    if (!isOnline) {
        document.getElementById('offlineBadge')?.classList.add('visible');
    }
    
    window.addEventListener('online', () => {
        document.getElementById('offlineBadge')?.classList.remove('visible');
        syncData();
    });
    
    window.addEventListener('offline', () => {
        document.getElementById('offlineBadge')?.classList.add('visible');
    });
    
    function syncData() {
        const btn = document.querySelector('button[onclick="syncData()"]');
        const icon = document.getElementById('syncIcon');
        const text = document.getElementById('syncText');
        
        btn.disabled = true;
        icon.classList.add('fa-spin');
        text.textContent = 'Syncing...';
        
        fetch('<?= SITE_URL ?>/api/sync.php?action=status')
            .then(r => r.json())
            .then(status => {
                if (status.pending > 0) {
                    return fetch('<?= SITE_URL ?>/api/sync.php?action=push');
                }
                return { json: () => ({ synced: 0 }) };
            })
            .then(r => r.json())
            .then(result => {
                showToast('Sync complete! ' + (result.synced || 0) + ' records synced');
            })
            .catch(e => {
                showToast('Sync failed: ' + e.message, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                icon.classList.remove('fa-spin');
                text.textContent = 'Sync';
            });
    }
    
    // Load classes on modal open
    document.getElementById('quickAddModal').addEventListener('shown.bs.modal', function() {
        loadQuickAddOptions('class');
    });
    
    // Quick Add Tab Switching
    document.querySelectorAll('#quickAddTabs button').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#quickAddTabs button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const type = this.dataset.type;
            document.querySelector('input[name="type"]').value = type;
            document.getElementById('btnType').textContent = this.textContent.trim();
            
            ['teacher', 'student', 'class', 'subject'].forEach(t => {
                document.getElementById(t + 'Fields').style.display = type === t ? 'block' : 'none';
            });
            
            if (type === 'subject' || type === 'student') {
                loadQuickAddOptions(type);
            }
        });
    });
    
    async function loadQuickAddOptions(type) {
        try {
            if (type === 'student') {
                const res = await fetch('<?= SITE_URL ?>/api/app.php?action=get_classes');
                const data = await res.json();
                const select = document.querySelector('#studentFields select[name="class_id"]');
                if (data.success && select) {
                    select.innerHTML = '<option value="">Select Class</option>' +
                        data.data.map(c => '<option value="' + c.id + '">' + c.name + '</option>').join('');
                }
            }
            if (type === 'subject') {
                const res1 = await fetch('<?= SITE_URL ?>/api/app.php?action=get_classes');
                const data1 = await res1.json();
                const clsSelect = document.querySelector('#subjectFields select[name="class_id"]');
                if (data1.success && clsSelect) {
                    clsSelect.innerHTML = '<option value="">Select Class</option>' +
                        data1.data.map(c => '<option value="' + c.id + '">' + c.name + '</option>').join('');
                }
                const res2 = await fetch('<?= SITE_URL ?>/api/app.php?action=get_teachers');
                const data2 = await res2.json();
                const tchSelect = document.querySelector('#subjectFields select[name="teacher_id"]');
                if (data2.success && tchSelect) {
                    tchSelect.innerHTML = '<option value="">Select Teacher</option>' +
                        data2.data.map(t => '<option value="' + t.id + '">' + t.first_name + ' ' + t.last_name + '</option>').join('');
                }
            }
        } catch(e) { console.error(e); }
    }
    
    // Quick Add Form Submit
    document.getElementById('quickAddForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Debug: Check if we're in the right section
        const type = this.querySelector('input[name="type"]').value;
        console.log('Quick Add Type:', type);
        
        const formData = new FormData();
        
        // Only include fields from active section, strip prefix
        const activeFields = document.getElementById(type + 'Fields');
        const prefix = type + '_';
        
        console.log('Active fields div:', activeFields);
        
        activeFields.querySelectorAll('input, select').forEach(input => {
            if (input.name && input.value) {
                // Strip the prefix (e.g., 'teacher_') to get the actual field name
                let fieldName = input.name;
                if (fieldName.startsWith(prefix)) {
                    fieldName = fieldName.substring(prefix.length);
                }
                console.log('Appending:', fieldName, '=', input.value);
                formData.append(fieldName, input.value);
            }
        });
        
        // Debug: Show what's being sent
        console.log('FormData entries:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }
        
        try {
            const res = await fetch('<?= SITE_URL ?>/api/app.php?action=quick_add_' + type, {
                method: 'POST',
                body: formData
            });
            
            console.log('Response status:', res.status);
            const data = await res.json();
            console.log('Response data:', data);
            
            if (data.success) {
                showToast(data.message);
                bootstrap.Modal.getInstance(document.getElementById('quickAddModal')).hide();
                this.reset();
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'Error adding ' + type, 'error');
            }
        } catch(e) {
            console.error('Fetch error:', e);
            showToast('Error: ' + e.message, 'error');
        }
    });
</script>
</body>
</html>