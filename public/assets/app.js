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

function toggleFolderActions(event) {
  event.stopPropagation();
  const menu = document.getElementById('folderActionsMenu');
  if (menu) menu.classList.toggle('show');
}

function openFolderActionModal(id) {
  closeFolderActions();
  openModal(id);
}

document.addEventListener('click', (event) => {
  if (!event.target.closest('.folder-actions')) {
    closeFolderActions();
  }
  if (event.target.classList && event.target.classList.contains('modal')) {
    event.target.classList.remove('show');
  }
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

function setValue(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = value == null ? '' : value;
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
  document.getElementById('userFormTitle').textContent = '编辑用户';
  setValue('user_id', user.id);
  setValue('user_username', user.username);
  setValue('user_email', user.email);
  setValue('user_real_name', user.real_name);
  setValue('user_workgroup_id', user.workgroup_id);
  setValue('user_member_unit_id', user.member_unit_id);
  setValue('user_role', user.role);
  setValue('user_status', user.status);
  document.querySelectorAll('#userForm input[name="directory_ids[]"]').forEach((input) => {
    input.checked = Array.isArray(user.permission_ids) && user.permission_ids.includes(Number(input.value));
  });
  openModal('userForm');
}

function newUser() {
  const form = document.querySelector('#userForm form');
  if (form) form.reset();
  document.getElementById('userFormTitle').textContent = '新增用户';
  setValue('user_id', '');
  setValue('user_role', 'member');
  setValue('user_status', 'active');
  document.querySelectorAll('#userForm input[name="directory_ids[]"]').forEach((input) => {
    input.checked = false;
  });
  openModal('userForm');
}

function fillUnit(unit) {
  setValue('unit_id', unit.id);
  setValue('unit_workgroup_id', unit.workgroup_id);
  setValue('unit_company_name', unit.company_name);
  setValue('unit_remark', unit.remark);
  openModal('unitForm');
}

function fillWorkgroup(workgroup) {
  document.getElementById('workgroupFormTitle').textContent = '编辑工作组';
  setValue('workgroup_id', workgroup.id);
  setValue('workgroup_name', workgroup.name);
  setValue('workgroup_description', workgroup.description);
  openModal('workgroupForm');
}

function newWorkgroup() {
  const form = document.querySelector('#workgroupForm form');
  if (form) form.reset();
  document.getElementById('workgroupFormTitle').textContent = '新增工作组';
  setValue('workgroup_id', '');
  setValue('workgroup_name', '');
  setValue('workgroup_description', '');
  openModal('workgroupForm');
  const input = document.getElementById('workgroup_name');
  if (input) input.focus();
}

function fillProposal(proposal) {
  document.getElementById('proposalFormTitle').textContent = '编辑提案';
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
  document.getElementById('proposalFormTitle').textContent = '新建提案';
  setValue('proposal_id', '');
  openModal('proposalForm');
}

document.addEventListener('change', (event) => {
  const target = event.target;
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
