function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('show');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('show');
}

function closeFolderActions() {
  const menu = document.getElementById('folderActionsMenu');
  if (menu) menu.classList.remove('show');
}

function closeAccountMenu() {
  const menu = document.getElementById('accountMenu');
  if (menu) menu.classList.remove('show');
}

function toggleAccountMenu(event) {
  event.stopPropagation();
  const menu = document.getElementById('accountMenu');
  if (menu) menu.classList.toggle('show');
}

function openChangePasswordModal() {
  closeAccountMenu();
  const form = document.querySelector('#changePasswordForm form');
  if (form) form.reset();
  openModal('changePasswordForm');
  const input = document.getElementById('current_password');
  if (input) input.focus();
}

function toggleFolderActions(event) {
  event.stopPropagation();
  const menu = document.getElementById('folderActionsMenu');
  if (menu) menu.classList.toggle('show');
}

function openFolderActionModal(id) {
  closeFolderActions();
  openModal(id);
}

const UI_TRANSLATIONS = {
  '星闪提案系统': 'SparkLink Proposal System',
  '▯ 星闪提案系统': '▯ SparkLink Proposal System',
  '提案文件': 'Proposal Files',
  '用户管理': 'User Management',
  '提案管理': 'Proposal Management',
  '修改密码': 'Change Password',
  '退出': 'Logout',
  '原密码 *': 'Current Password *',
  '新密码 *': 'New Password *',
  '确认新密码 *': 'Confirm New Password *',
  '取消': 'Cancel',
  '保存': 'Save',
  '确定': 'OK',
  '复制': 'Copy',
  '编辑': 'Edit',
  '删除': 'Delete',
  '删': 'Del',
  '查询': 'Search',
  '重置': 'Reset',
  '下载': 'Download',
  '重命名': 'Rename',
  '移动到': 'Move To',
  '上传': 'Upload',
  '新增': 'Add',
  '驳回': 'Reject',
  '文件夹操作': 'Folder Actions',
  '增加子文件夹': 'Add Subfolder',
  '添加文件': 'Add File',
  '文件夹结构': 'Folder Structure',
  '展开所有文件夹': 'Expand All Folders',
  '折叠所有文件夹': 'Collapse All Folders',
  '上传到当前文件夹': 'Upload to Current Folder',
  '文件夹名称 *': 'Folder Name *',
  '文件夹名称': 'Folder Name',
  '重命名文件夹': 'Rename Folder',
  '复制文件夹': 'Copy Folder',
  '复制到目标父文件夹': 'Copy to Parent Folder',
  '移动文件夹': 'Move Folder',
  '移动到目标父文件夹': 'Move to Parent Folder',
  '删除文件夹': 'Delete Folder',
  '只能删除空文件夹。确定删除当前文件夹吗？': 'Only empty folders can be deleted. Delete the current folder?',
  '重命名文件': 'Rename File',
  '移动文件': 'Move File',
  '当前文件': 'Current File',
  '目标文件夹 *': 'Target Folder *',
  '文件名': 'File Name',
  '大小': 'Size',
  '上传时间': 'Upload Time',
  '上传人': 'Uploader',
  '操作': 'Actions',
  '暂无文件': 'No Files',
  '正式会员': 'Members',
  '待审核': 'Pending Review',
  '工作组管理': 'Workgroups',
  '会员单位': 'Member Units',
  '所有工作组': 'All Workgroups',
  '所有角色': 'All Roles',
  '所有会议地点': 'All Meeting Places',
  '新增用户': 'Add User',
  '编辑用户': 'Edit User',
  '审核注册申请': 'Review Registration',
  '重置密码': 'Reset Password',
  '序号': 'No.',
  '编号': 'ID',
  '用户名': 'Username',
  '邮箱': 'Email',
  '姓名': 'Name',
  '公司名称': 'Company',
  '工作组': 'Workgroup',
  '角色': 'Role',
  '状态': 'Status',
  '会员单位': 'Member Unit',
  '申请时间': 'Applied At',
  '暂无用户': 'No Users',
  '用户名 *': 'Username *',
  '邮箱 *': 'Email *',
  '姓名 *': 'Name *',
  '工作组 *': 'Workgroup *',
  '会员单位 *': 'Member Unit *',
  '首席会员 *': 'Chief Member *',
  '角色 *': 'Role *',
  '状态 *': 'Status *',
  '文件夹权限设置': 'Folder Permissions',
  '超管': 'Super Admin',
  '管理员': 'Admin',
  '首席会员': 'Chief Member',
  '普通会员': 'Member',
  '启用': 'Enabled',
  '禁用': 'Disabled',
  '超管账户': 'Super Admin Account',
  '管理员账户': 'Admin Account',
  '密码已重置': 'Password Reset',
  '新密码': 'New Password',
  '工作组名称': 'Workgroup Name',
  '工作组名称 *': 'Workgroup Name *',
  '描述': 'Description',
  '新增工作组': 'Add Workgroup',
  '编辑工作组': 'Edit Workgroup',
  '暂无工作组': 'No Workgroups',
  '备注': 'Remark',
  '公司 *': 'Company *',
  '新增会员单位': 'Add Member Unit',
  '编辑会员单位': 'Edit Member Unit',
  '提案任务': 'Proposal Tasks',
  '新建提案': 'New Proposal',
  '编辑提案': 'Edit Proposal',
  '会议时间': 'Meeting Date',
  '会议时间 *': 'Meeting Date *',
  '会议地点': 'Meeting Place',
  '会议地点 *': 'Meeting Place *',
  '会议主题': 'Meeting Subject',
  '会议主题 *': 'Meeting Subject *',
  '会议编号 *': 'Meeting Code *',
  '提案号': 'Proposal No.',
  '提案号 *': 'Proposal No. *',
  '存储目录': 'Storage Directory',
  '存储目录 *': 'Storage Directory *',
  '有效期': 'Due Date',
  '有效期 *': 'Due Date *',
  '上传文件': 'Upload File',
  '已过期': 'Expired',
  '暂无提案任务': 'No Proposal Tasks',
  '默认为创建后7天': 'Default is 7 days after creation',
  '选择一个文件夹作为提案的存储目录': 'Select a folder as the proposal storage directory',
  '搜索公司、姓名、邮箱、用户名...': 'Search company, name, email, username...',
  '搜索工作组名称、描述...': 'Search workgroup name, description...',
  '搜索公司名称、备注...': 'Search company name, remark...',
  '搜索会议主题、会议编号、提案号、存储目录...': 'Search subject, meeting code, proposal no., storage...'
};

const EN_TO_UI_TRANSLATIONS = Object.fromEntries(
  Object.entries(UI_TRANSLATIONS).map(([zh, en]) => [en, zh])
);

function currentLanguage() {
  return localStorage.getItem('tian_language') === 'en' ? 'en' : 'zh';
}

function translateString(value, lang) {
  const dict = lang === 'en' ? UI_TRANSLATIONS : EN_TO_UI_TRANSLATIONS;
  const text = String(value);
  const trimmed = text.trim();
  if (!trimmed) return value;
  if (dict[trimmed]) {
    return text.replace(trimmed, dict[trimmed]);
  }
  const prefixed = trimmed.match(/^(\S+\s+)(.+)$/);
  if (prefixed && dict[prefixed[2]]) {
    return text.replace(trimmed, prefixed[1] + dict[prefixed[2]]);
  }
  return value;
}

function shouldSkipTranslation(node) {
  const element = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;
  if (!element) return true;
  return Boolean(element.closest('script, style, textarea, .language-switch, .tree, .file-name, .upload-chip'));
}

function applyLanguage(lang = currentLanguage()) {
  document.documentElement.lang = lang === 'en' ? 'en' : 'zh-CN';
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      return shouldSkipTranslation(node) ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
    }
  });
  const nodes = [];
  while (walker.nextNode()) nodes.push(walker.currentNode);
  nodes.forEach((node) => {
    node.nodeValue = translateString(node.nodeValue, lang);
  });
  document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach((el) => {
    el.placeholder = translateString(el.placeholder, lang);
  });
  document.querySelectorAll('[title], [aria-label]').forEach((el) => {
    if (shouldSkipTranslation(el)) return;
    if (el.title) el.title = translateString(el.title, lang);
    const aria = el.getAttribute('aria-label');
    if (aria) el.setAttribute('aria-label', translateString(aria, lang));
  });
}

function setLocalizedText(id, zhText) {
  const el = document.getElementById(id);
  if (el) el.textContent = currentLanguage() === 'en' ? (UI_TRANSLATIONS[zhText] || zhText) : zhText;
}

function setLanguagePreference(value) {
  const lang = value === 'en' ? 'en' : 'zh';
  localStorage.setItem('tian_language', lang);
  applyLanguage(lang);
}

document.addEventListener('click', (event) => {
  if (!event.target.closest('.folder-actions')) {
    closeFolderActions();
  }
  if (!event.target.closest('.account-menu')) {
    closeAccountMenu();
  }
});

document.addEventListener('DOMContentLoaded', () => {
  const languageSelect = document.getElementById('languageSelect');
  if (!languageSelect) return;
  const savedLanguage = localStorage.getItem('tian_language') || 'zh';
  languageSelect.value = savedLanguage === 'en' ? 'en' : 'zh';
  setLanguagePreference(languageSelect.value);
});

function postForm(action, values) {
  const form = document.createElement('form');
  form.method = 'post';
  form.action = action;
  Object.entries(values).forEach(([name, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value == null ? '' : value;
    form.appendChild(input);
  });
  document.body.appendChild(form);
  form.submit();
}

function promptPost(action, values, message, fieldName, defaultValue) {
  const value = prompt(message, defaultValue || '');
  if (value === null) return;
  postForm(action, Object.assign({}, values, { [fieldName]: value }));
}

function copyResetPassword() {
  const input = document.getElementById('reset_password_value');
  if (!input) return;
  input.select();
  input.setSelectionRange(0, input.value.length);
  const copied = navigator.clipboard
    ? navigator.clipboard.writeText(input.value)
    : Promise.reject(new Error('clipboard unavailable'));
  copied.catch(() => document.execCommand('copy'));
}

function setValue(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = value == null ? '' : value;
}

function setDisabled(id, disabled) {
  const el = document.getElementById(id);
  if (el) el.disabled = disabled;
}

function findOptionByText(selectId, text) {
  const select = document.getElementById(selectId);
  if (!select) return null;
  return Array.from(select.options).find((option) => option.textContent.trim() === text) || null;
}

function applySuperAdminLock(locked) {
  setDisabled('user_username', locked);
  setDisabled('user_member_unit_id', locked);
  setDisabled('user_role', locked);
  setDisabled('user_status', locked);
  applyDirectoryPermissionLock(locked);
  if (locked) {
    const alliance = findOptionByText('user_member_unit_id', '星闪联盟');
    if (alliance) setValue('user_member_unit_id', alliance.value);
    setValue('user_role', 'super_admin');
    setValue('user_status', 'active');
  }
}

function applyDirectoryPermissionLock(locked) {
  document.querySelectorAll('#userForm input[name="directory_ids[]"]').forEach((input) => {
    if (locked) input.checked = true;
    input.disabled = locked;
  });
}

function syncRolePermissions() {
  const role = document.getElementById('user_role')?.value;
  applyDirectoryPermissionLock(role === 'admin' || role === 'super_admin');
}

function openRenameFileModal(file) {
  setValue('rename_file_id', file.id);
  setValue('rename_file_name', file.name);
  openModal('renameFileForm');
  const input = document.getElementById('rename_file_name');
  if (input) {
    input.focus();
    input.select();
  }
}

function openMoveFileModal(file) {
  setValue('move_file_id', file.id);
  setValue('move_file_name', file.name);
  document.querySelectorAll('#moveFileForm input[name="directory_id"]').forEach((input) => {
    input.checked = Number(input.value) === Number(file.directory_id);
  });
  openModal('moveFileForm');
}

function fillUser(user) {
  setLocalizedText('userFormTitle', '编辑用户');
  applySuperAdminLock(false);
  setValue('user_id', user.id);
  setValue('user_username', user.username);
  setValue('user_email', user.email);
  setValue('user_real_name', user.real_name);
  setValue('user_workgroup_id', user.workgroup_id);
  setValue('user_member_unit_id', user.member_unit_id);
  setValue('user_role', user.role);
  setValue('user_status', user.status);
  const isSuperAdmin = user.role === 'super_admin';
  const hasAllDirectories = isSuperAdmin || user.role === 'admin';
  document.querySelectorAll('#userForm input[name="directory_ids[]"]').forEach((input) => {
    input.checked = hasAllDirectories || (Array.isArray(user.permission_ids) && user.permission_ids.includes(Number(input.value)));
  });
  applySuperAdminLock(isSuperAdmin);
  syncRolePermissions();
  openModal('userForm');
}

function reviewUser(user) {
  fillUser(user);
  setLocalizedText('userFormTitle', '审核注册申请');
}

function newUser() {
  const form = document.querySelector('#userForm form');
  if (form) form.reset();
  applySuperAdminLock(false);
  setLocalizedText('userFormTitle', '新增用户');
  setValue('user_id', '');
  setValue('user_role', 'member');
  setValue('user_status', 'active');
  document.querySelectorAll('#userForm input[name="directory_ids[]"]').forEach((input) => {
    input.checked = false;
    input.disabled = false;
  });
  syncRolePermissions();
  openModal('userForm');
}

function fillUnit(unit) {
  setLocalizedText('unitFormTitle', '编辑会员单位');
  setValue('unit_id', unit.id);
  setValue('unit_workgroup_id', unit.workgroup_id);
  setValue('unit_company_name', unit.company_name);
  setValue('unit_remark', unit.remark);
  setDisabled('unit_company_name', unit.company_name === '星闪联盟');
  openModal('unitForm');
}

function newUnit() {
  const form = document.querySelector('#unitForm form');
  if (form) form.reset();
  setLocalizedText('unitFormTitle', '新增会员单位');
  setValue('unit_id', '');
  setValue('unit_workgroup_id', '');
  setValue('unit_company_name', '');
  setValue('unit_remark', '');
  setDisabled('unit_company_name', false);
  openModal('unitForm');
  const select = document.getElementById('unit_workgroup_id');
  if (select) select.focus();
}

function fillWorkgroup(workgroup) {
  setLocalizedText('workgroupFormTitle', '编辑工作组');
  setValue('workgroup_id', workgroup.id);
  setValue('workgroup_name', workgroup.name);
  setValue('workgroup_description', workgroup.description);
  openModal('workgroupForm');
}

function newWorkgroup() {
  const form = document.querySelector('#workgroupForm form');
  if (form) form.reset();
  setLocalizedText('workgroupFormTitle', '新增工作组');
  setValue('workgroup_id', '');
  setValue('workgroup_name', '');
  setValue('workgroup_description', '');
  openModal('workgroupForm');
  const input = document.getElementById('workgroup_name');
  if (input) input.focus();
}

function fillProposal(proposal) {
  setLocalizedText('proposalFormTitle', '编辑提案');
  setValue('proposal_id', proposal.id);
  setValue('proposal_meeting_date', proposal.meeting_date);
  setValue('proposal_meeting_place', proposal.meeting_place);
  setValue('proposal_meeting_subject', proposal.meeting_subject);
  setValue('proposal_workgroup_id', proposal.workgroup_id);
  setValue('proposal_member_unit_id', proposal.member_unit_id);
  setValue('proposal_chief_user_id', proposal.chief_user_id);
  setValue('proposal_meeting_code', proposal.meeting_code);
  setValue('proposal_proposal_code', proposal.proposal_code);
  setValue('proposal_due_date', proposal.due_date);
  setValue('proposal_description', proposal.description);
  document.querySelectorAll('#proposalForm input[name="directory_id"]').forEach((input) => {
    input.checked = Number(input.value) === Number(proposal.directory_id);
  });
  openModal('proposalForm');
}

function newProposal() {
  const form = document.querySelector('#proposalForm form');
  if (form) form.reset();
  setLocalizedText('proposalFormTitle', '新建提案');
  setValue('proposal_id', '');
  openModal('proposalForm');
}

document.addEventListener('change', (event) => {
  const target = event.target;
  if (target.matches('#user_role')) {
    syncRolePermissions();
    return;
  }
  if (!target.matches('.tree input[type="checkbox"]')) return;
  const li = target.closest('li');
  if (!li) return;
  li.querySelectorAll('input[type="checkbox"]').forEach((child) => {
    child.checked = target.checked;
  });
});

const TREE_STATE_KEY = 'tian.openDirectories';

function readTreeState() {
  try {
    return JSON.parse(localStorage.getItem(TREE_STATE_KEY) || '{}');
  } catch (error) {
    return {};
  }
}

function writeTreeState(state) {
  localStorage.setItem(TREE_STATE_KEY, JSON.stringify(state));
}

function setTreeNodeOpen(li, open) {
  li.classList.toggle('is-open', open);
  li.classList.toggle('is-collapsed', !open);
  const toggle = li.querySelector(':scope > .tree-node > .tree-toggle');
  if (toggle) {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.setAttribute('aria-label', open ? '折叠' : '展开');
  }
}

function treeNodesWithChildren() {
  return Array.from(document.querySelectorAll('.tree li[data-dir-id]')).filter((li) => {
    return li.querySelector(':scope > .tree-node > .tree-toggle');
  });
}

function expandAllDirectories() {
  const state = readTreeState();
  treeNodesWithChildren().forEach((li) => {
    setTreeNodeOpen(li, true);
    state[li.dataset.dirId] = true;
  });
  writeTreeState(state);
}

function collapseAllDirectories() {
  const state = readTreeState();
  treeNodesWithChildren().forEach((li) => {
    const parentList = li.parentElement;
    const isTopLevel = parentList?.parentElement?.classList?.contains('tree');
    setTreeNodeOpen(li, isTopLevel);
    state[li.dataset.dirId] = isTopLevel;
  });
  writeTreeState(state);
}

document.querySelectorAll('.tree li[data-dir-id]').forEach((li) => {
  const state = readTreeState();
  const id = li.dataset.dirId;
  if (Object.prototype.hasOwnProperty.call(state, id) && !li.querySelector('a.active')) {
    setTreeNodeOpen(li, state[id]);
  }
});

document.addEventListener('click', (event) => {
  const toggle = event.target.closest('.tree-toggle');
  if (!toggle) return;
  const li = toggle.closest('li[data-dir-id]');
  if (!li) return;
  const open = !li.classList.contains('is-open');
  setTreeNodeOpen(li, open);
  const state = readTreeState();
  state[li.dataset.dirId] = open;
  writeTreeState(state);
});
