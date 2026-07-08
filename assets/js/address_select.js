/**
 * Lanto Web - Dynamic Address Dropdown & Zipcode Controller
 * ระบบดึงข้อมูลที่อยู่จริงผ่าน JSON สไตล์ Light Glassmorphism ขอบมนลึก
 */
document.addEventListener('DOMContentLoaded', function() {
    const provinceSelect = document.getElementById('province');
    const districtSelect = document.getElementById('district');
    const subdistrictSelect = document.getElementById('subdistrict');
    const zipcodeField = document.getElementById('zipcode');

    // 1. โหลดข้อมูลจังหวัดทั้งหมดมารอไว้ตอนเปิดหน้าเว็บ
    fetch('assets/data/provinces.json')
        .then(response => response.json())
        .then(provinces => {
            provinceSelect.innerHTML = '<option value="" class="rounded-xl">เลือกจังหวัด</option>';
            provinces.forEach(prov => {
                // สมมติว่าโครงสร้างไฟล์มีชื่อจังหวัดอยู่ในตัวแปร name_th หรือ name (ปรับให้ตรงกับไฟล์ของคุณได้ครับ)
                const provName = prov.name_th || prov.name;
                const provId = prov.id;
                appendOption(provinceSelect, provName, provId);
            });
        })
        .catch(err => console.error('ไม่สามารถโหลดข้อมูลจังหวัดได้:', err));

    // 2. เมื่อพนักงานเลือก "จังหวัด" -> ไปค้นหา "อำเภอ/เขต" ที่สังกัดจังหวัดนั้นมาแสดง
    provinceSelect.addEventListener('change', function() {
        const provinceId = this.value;
        
        // ล้างค่ารอไว้ก่อน
        districtSelect.innerHTML = '<option value="">เลือกอำเภอ/เขต</option>';
        subdistrictSelect.innerHTML = '<option value="">เลือกตำบล/แขวง</option>';
        zipcodeField.value = '';

        if (!provinceId) return;

        fetch('assets/data/districts.json')
            .then(response => response.json())
            .then(districts => {
                // กรองเฉพาะอำเภอที่มีรหัสจังหวัด (province_id) ตรงกับที่เลือก
                const filteredDistricts = districts.filter(dist => dist.province_id == provinceId);
                
                filteredDistricts.forEach(dist => {
                    const distName = dist.name_th || dist.name;
                    const distId = dist.id;
                    appendOption(districtSelect, distName, distId);
                });
            });
    });

    // 3. เมื่อพนักงานเลือก "อำเภอ" -> ไปค้นหา "ตำบล/แขวง" และดึง "รหัสไปรษณีย์" มาใส่ให้ทันที
    districtSelect.addEventListener('change', function() {
        const districtId = this.value;
        
        subdistrictSelect.innerHTML = '<option value="">เลือกตำบล/แขวง</option>';
        zipcodeField.value = '';

        if (!districtId) return;

        fetch('assets/data/subdistricts.json')
            .then(response => response.json())
            .then(subdistricts => {
                // กรองเฉพาะตำบลที่มีรหัสอำเภอ (amphure_id หรือ district_id) ตรงกัน
                const filteredSubdistricts = subdistricts.filter(sub => (sub.amphure_id == districtId || sub.district_id == districtId));
                
                filteredSubdistricts.forEach(sub => {
                    const subName = sub.name_th || sub.name;
                    // ฝังรหัสไปรษณีย์ไว้ที่ตัว value เลยเพื่อให้ดึงไปใช้ง่ายๆ หรือผูกผ่าน ID
                    const zipCode = sub.zip_code || sub.zipcode || '';
                    appendOption(subdistrictSelect, subName, subName, zipCode); 
                });
            });
    });

    // 4. เมื่อพนักงานเลือก "ตำบล" -> ให้เอารหัสไปรษณีย์ที่แอบหยอดไว้มาเติมลงในช่องอัตโนมัติ
    subdistrictSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const zip = selectedOption.getAttribute('data-zip') || '';
        zipcodeField.value = zip;
    });

    // ฟังก์ชันเสริมช่วยสร้างตัวเลือกดร็อปดาวน์ขอบมนลึก สวยเนียนเข้ากับ Tailwind v4
    function appendOption(selectElement, text, value, zip = '') {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = text;
        if (zip) {
            opt.setAttribute('data-zip', zip);
        }
        // ตกแต่งตัวเลือกภายในให้สุภาพและอ่านง่าย
        opt.className = 'bg-white text-slate-800 p-2';
        selectElement.appendChild(opt);
    }
});